<?php

declare(strict_types=1);

namespace App\Services;

use App\Builders\TenantBuilder;
use App\Contracts\TenantManagerInterface;
use App\Events\PlanChanged;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\States\TenantStateManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TenantManager implements TenantManagerInterface
{
    public function provision(array $data): Tenant
    {
        return (new TenantBuilder($data))
            ->withDomain($data['domain'])
            ->withPlan($data['plan'] ?? null)
            ->build();
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

        TenantStateManager::flushTenantCache($tenant);
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

        TenantStateManager::flushTenantCache($tenant);
    }

    public function delete(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant) {
            $tenant->activeSubscription?->update([
                'status' => 'cancelled',
                'ends_at' => now(),
            ]);

            TenantStateManager::transitionTo($tenant, 'Deleted');

            $tenant->delete();
        });

        TenantStateManager::flushTenantCache($tenant);
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
    }

    public function changePlan(Tenant $tenant, Plan $newPlan): void
    {
        $oldPlan = null;

        DB::transaction(function () use ($tenant, $newPlan, &$oldPlan) {
            if (! $tenant->plan) {
                $defaultSlug = config('tenancy.default_plan_slug', 'free');
                $oldPlan = Plan::firstOrCreate(
                    ['slug' => $defaultSlug],
                    ['name' => 'Free Plan', 'price' => 0, 'slug' => $defaultSlug]
                );
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

            event(new PlanChanged($tenant, $oldPlan, $newPlan));
        });

        TenantStateManager::flushTenantCache($tenant);
    }

    public function createSubscription(Tenant $tenant, Plan $plan, string $status, ?Carbon $endsAt = null, ?Carbon $startsAt = null): Subscription
    {
        return DB::transaction(function () use ($tenant, $plan, $status, $endsAt, $startsAt) {
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

            if ($oldPlan && $oldPlan->id !== $plan->id) {
                event(new PlanChanged($tenant, $oldPlan, $plan));
            }

            TenantStateManager::flushTenantCache($tenant);

            return $subscription;
        });
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
}
