<?php

declare(strict_types=1);

namespace App\Tenants;

use App\Billing\Events\PlanChanged;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Tenants\Contracts\TenantBuilderInterface;
use App\Tenants\Contracts\TenantManagerInterface;
use App\Tenants\Events\TenantReactivated;
use App\Tenants\Events\TenantSuspended;
use App\Tenants\States\TenantStateManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class TenantManager implements TenantManagerInterface
{
    public function __construct(
        private TenantBuilderInterface $tenantBuilder,
    ) {}

    public function provision(array $data): Tenant
    {
        return DB::transaction(function () use ($data) {
            return $this->tenantBuilder
                ->withData($data)
                ->withDomain($data['domain'])
                ->withPlan($data['plan'] ?? null)
                ->build();
        });
    }

    public function suspend(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant) {
            $tenant->activeSubscription?->update([
                'status' => 'cancelled',
                'ends_at' => now(),
            ]);

            TenantStateManager::transitionTo($tenant, 'Suspended');
        });

        TenantSuspended::dispatch($tenant);
        TenantStateManager::flushTenantCache($tenant);
        Cache::forget('admin_dashboard_stats_active');
        Cache::forget('admin_dashboard_stats_trashed');
    }

    public function activate(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant) {
            $tenant->subscriptions()
                ->where('status', 'cancelled')
                ->latest()
                ->first()
                ?->update([
                    'status' => 'active',
                    'ends_at' => null,
                ]);

            TenantStateManager::transitionTo($tenant, 'Active');
        });

        TenantReactivated::dispatch($tenant);
        TenantStateManager::flushTenantCache($tenant);
        Cache::forget('admin_dashboard_stats_active');
        Cache::forget('admin_dashboard_stats_trashed');
    }

    public function delete(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant) {
            $tenant->activeSubscription?->update([
                'status' => 'cancelled',
                'ends_at' => now(),
            ]);

            TenantStateManager::transitionTo($tenant, 'Deleted');
        });

        // $tenant->delete() must run AFTER the transaction because it triggers
        // TenantDeleted -> DeleteDatabase -> DROP DATABASE (MySQL DDL).
        // MySQL implicitly commits any active transaction when executing DDL,
        // which would cause PDO::commit() to throw "no active transaction".
        $tenant->delete();

        TenantStateManager::flushTenantCache($tenant);
        Cache::forget('admin_dashboard_stats_active');
        Cache::forget('admin_dashboard_stats_trashed');
    }

    public function restore(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant) {
            // Restore tenant first so Domain tenant() relation works (Tenant model uses SoftDeletes)
            $tenant->deleted_at = null;
            $tenant->save();

            $tenant->subscriptions()
                ->where('status', 'cancelled')
                ->where('plan_id', $tenant->plan_id)
                ->latest()
                ->first()
                ?->update([
                    'status' => 'active',
                    'ends_at' => null,
                ]);

            // If no domains exist, create a primary one
            if ($tenant->domains()->count() === 0) {
                $tenant->domains()->create([
                    'domain' => $tenant->domain ?? $tenant->id.'.localhost',
                    'is_primary' => true,
                ]);
            }

            TenantStateManager::transitionTo($tenant, 'Active');
        });

        TenantStateManager::flushTenantCache($tenant);
        Cache::forget('admin_dashboard_stats_active');
        Cache::forget('admin_dashboard_stats_trashed');
    }

    public function changePlan(Tenant $tenant, Plan $newPlan): void
    {
        if (! in_array($tenant->status, ['Active', 'Trial'], true)) {
            throw new InvalidArgumentException(
                'Cannot change plan for a tenant with status: '.$tenant->status
            );
        }

        $oldPlan = null;

        DB::transaction(function () use ($tenant, $newPlan, &$oldPlan) {
            $tenant = Tenant::lockForUpdate()->findOrFail($tenant->id);

            if (! $tenant->plan) {
                $defaultSlug = config('tenancy.default_plan_slug', 'free');
                $oldPlan = Plan::where('slug', $defaultSlug)->firstOrFail();
            } else {
                $oldPlan = $tenant->plan;
            }

            $tenant->activeSubscription?->update([
                'status' => 'cancelled',
                'ends_at' => now()->subDay(),
            ]);

            Subscription::createForTenant($tenant, $newPlan, 'active');

            $tenant->plan_id = $newPlan->id;
            $tenant->save();
        });

        event(new PlanChanged($tenant, $oldPlan, $newPlan));
        TenantStateManager::flushTenantCache($tenant);
    }

    public function createSubscription(Tenant $tenant, Plan $plan, string $status, ?Carbon $endsAt = null, ?Carbon $startsAt = null): Subscription
    {
        $oldPlan = null;

        $subscription = DB::transaction(function () use ($tenant, $plan, $status, $endsAt, $startsAt, &$oldPlan) {
            $tenant = Tenant::lockForUpdate()->findOrFail($tenant->id);
            $oldPlan = $tenant->plan;

            $tenant->activeSubscription?->update([
                'status' => 'cancelled',
                'ends_at' => now()->subDay(),
            ]);

            $subscription = Subscription::createForTenant(
                $tenant,
                $plan,
                $status,
                $endsAt,
                $startsAt,
            );

            $tenant->plan_id = $plan->id;
            $tenant->save();

            return $subscription;
        });

        if ($oldPlan && $oldPlan->id !== $plan->id) {
            event(new PlanChanged($tenant, $oldPlan, $plan));
        }

        TenantStateManager::flushTenantCache($tenant);

        return $subscription;
    }

    public function setStatus(Tenant $tenant, string $status): void
    {
        match ($status) {
            'Active' => $this->activate($tenant),
            'Suspended' => $this->suspend($tenant),
            'Deleted' => $this->delete($tenant),
            default => TenantStateManager::transitionTo($tenant, $status),
        };
    }

    public function extendTrial(Tenant $tenant, int $days): void
    {
        if ($tenant->status !== 'Trial') {
            throw new InvalidArgumentException('Can only extend trial for tenants in Trial status.');
        }

        $currentEnd = $tenant->trial_ends_at ? $tenant->trial_ends_at->copy() : now();
        $tenant->trial_ends_at = $currentEnd->addDays($days);
        $tenant->save();

        TenantStateManager::flushTenantCache($tenant);
    }

    public function migrateTenant(Tenant $tenant): array
    {
        $exitCode = \Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
        ]);

        $output = \Artisan::output();

        Log::info('Tenant migration executed', [
            'tenant_id' => $tenant->id,
            'exit_code' => $exitCode,
        ]);

        return [
            'exit_code' => $exitCode,
            'output' => $output,
        ];
    }
}
