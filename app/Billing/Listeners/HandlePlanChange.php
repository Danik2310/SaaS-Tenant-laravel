<?php

declare(strict_types=1);

namespace App\Billing\Listeners;

use App\Billing\Events\PlanChanged;
use App\Models\TenantResourceUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandlePlanChange
{
    public function handle(PlanChanged $event): void
    {
        $tenant = $event->tenant;
        $old = $event->oldPlan;
        $new = $event->newPlan;

        $oldPrice = $old->price ?? 0;
        $newPrice = $new->price ?? 0;
        $direction = $newPrice < $oldPrice ? 'downgrade' : ($newPrice > $oldPrice ? 'upgrade' : 'lateral');

        $lostFeatures = $direction === 'downgrade'
            ? array_diff($old->features ?? [], $new->features ?? [])
            : [];

        Log::info('Tenant plan changed', [
            'tenant_id' => $tenant->id,
            'old_plan' => $old->slug,
            'new_plan' => $new->slug,
            'direction' => $direction,
            'lost_features' => $lostFeatures,
        ]);

        if ($direction !== 'downgrade') {
            return;
        }

        $exceeded = [];

        foreach (['users', 'storage', 'warehouses', 'categories', 'products'] as $resource) {
            $oldLimit = $old->getLimit($resource);
            $newLimit = $new->getLimit($resource);

            if ($newLimit >= $oldLimit) {
                continue;
            }

            $currentCount = $this->getCurrentCount($tenant, $resource);
            if ($currentCount > $newLimit) {
                $exceeded[$resource] = [
                    'current' => $currentCount,
                    'new_limit' => $newLimit,
                    'old_limit' => $oldLimit === PHP_INT_MAX ? 'unlimited' : $oldLimit,
                ];
            }
        }

        if (! empty($exceeded)) {
            Log::warning('Tenant downgrade resulted in exceeded limits', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'old_plan' => $old->slug,
                'new_plan' => $new->slug,
                'exceeded_limits' => $exceeded,
            ]);
        }

        if (! empty($lostFeatures)) {
            Log::warning('Tenant lost features on downgrade', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'lost_features' => $lostFeatures,
                'old_plan' => $old->slug,
                'new_plan' => $new->slug,
            ]);
        }
    }

    private function getCurrentCount($tenant, string $resource): int
    {
        return match ($resource) {
            'users' => $this->countInTenantDb($tenant, 'users'),
            'storage' => $this->getStorageCount($tenant),
            'warehouses' => $this->countInTenantDb($tenant, 'warehouses'),
            'categories' => $this->countInTenantDb($tenant, 'categories'),
            'products' => $this->countInTenantDb($tenant, 'products'),
            default => 0,
        };
    }

    private function getStorageCount($tenant): int
    {
        try {
            $usage = TenantResourceUsage::where('tenant_id', $tenant->id)->first();

            return (int) ($usage ? $usage->storage_kb : 0);
        } catch (\Throwable $e) {
            Log::warning("Failed to get storage count for tenant {$tenant->id}", [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    private function countInTenantDb($tenant, string $table): int
    {
        try {
            tenancy()->initialize($tenant);
            $count = DB::table($table)->count();
            tenancy()->end();

            return $count;
        } catch (\Throwable $e) {
            Log::warning("Failed to count {$table} for tenant {$tenant->id}", [
                'error' => $e->getMessage(),
            ]);

            if (tenancy()->initialized) {
                tenancy()->end();
            }

            return 0;
        }
    }
}
