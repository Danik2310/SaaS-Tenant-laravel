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
use Illuminate\Support\Facades\Cache;
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

            // Detach domains so they don't block re-use
            $tenant->domains()->delete();

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

            // Re-create primary domain record (the domain column may be null for legacy tenants)
            $tenant->domains()->create([
                'domain' => $tenant->domain ?? $tenant->id.'.localhost',
                'is_primary' => true,
            ]);

            TenantStateManager::transitionTo($tenant, 'Active');
        });

        TenantStateManager::flushTenantCache($tenant);
    }

    public function changePlan(Tenant $tenant, Plan $newPlan): void
    {
        $oldPlan = null;

        DB::transaction(function () use ($tenant, $newPlan, &$oldPlan) {
            $oldPlan = $tenant->plan ?? Plan::where('slug', 'free')->firstOrCreate(
                ['slug' => 'free'],
                ['name' => 'Free Plan', 'price' => 0]
            );

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
}

