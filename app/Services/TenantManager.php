<?php

declare(strict_types=1);

namespace App\Services;

use App\Builders\TenantBuilder;
use App\Contracts\TenantManagerInterface;
use App\Events\PlanChanged;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\States\TenantStateManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $tenant->activeSubscription?->update([
            'status' => 'cancelled',
            'ends_at' => now(),
        ]);

        TenantStateManager::transitionTo($tenant, 'Suspended');
    }

    public function delete(Tenant $tenant): void
    {
        $tenant->activeSubscription?->update([
            'status' => 'cancelled',
            'ends_at' => now(),
        ]);

        TenantStateManager::transitionTo($tenant, 'Deleted');
        $tenant->delete();
    }

    public function restore(Tenant $tenant): void
    {
        TenantStateManager::transitionTo($tenant, 'Active');
        $tenant->deleted_at = null;
        $tenant->save();

        $tenant->subscriptions()
            ->where('status', 'cancelled')
            ->latest()
            ->first()
            ?->update([
                'status' => 'active',
                'ends_at' => null,
            ]);
    }

    public function changePlan(Tenant $tenant, Plan $newPlan): void
    {
        DB::transaction(function () use ($tenant, $newPlan) {
            $oldPlan = $tenant->plan ?? Plan::where('slug', 'free')->firstOrNew([]);

            $tenant->activeSubscription?->update([
                'status' => 'cancelled',
                'ends_at' => now()->subDay(),
            ]);

            Subscription::createForTenant($tenant, $newPlan, 'active');

            $tenant->plan_id = $newPlan->id;
            $tenant->save();

            try {
                Cache::tags(['tenant_'.$tenant->id])->flush();
            } catch (\BadMethodCallException $e) {
                Log::warning('Cache driver does not support tags, skipped flush for tenant '.$tenant->id);
            }

            event(new PlanChanged($tenant, $oldPlan, $newPlan));
        });
    }
}
