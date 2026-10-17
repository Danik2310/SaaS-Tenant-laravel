<?php

namespace App\Plans\Support;

use App\Models\Tenant;
use App\Shared\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates the per-tenant `plan_data` Inertia prop cache.
 *
 * {@see HandleInertiaRequests} caches each tenant's plan
 * name/features/limits for an hour under the `tenant_{id}` cache tag. Editing a
 * plan — or renaming a feature flag, which cascades onto every plan that uses it
 * — otherwise leaves tenants looking at stale features and limits until that
 * entry expires.
 */
class TenantPlanCache
{
    /**
     * Forget the cached plan data of every tenant on the given plan.
     */
    public static function forgetForPlan(int $planId): void
    {
        Tenant::query()
            ->where('plan_id', $planId)
            ->pluck('id')
            ->each(fn (string $tenantId) => self::forget($tenantId));
    }

    /**
     * Forget the cached plan data of every tenant whose plan references the flag.
     *
     * Used when a feature flag key is renamed: the cascade onto `plan_features`
     * changes the feature list each affected tenant is entitled to.
     */
    public static function forgetForFeature(string $featureKey): void
    {
        Tenant::query()
            ->whereHas('plan.featureGates', fn ($query) => $query->where('feature_key', $featureKey))
            ->pluck('id')
            ->each(fn (string $tenantId) => self::forget($tenantId));
    }

    private static function forget(string $tenantId): void
    {
        try {
            Cache::tags(['tenant_'.$tenantId])->forget("tenant.{$tenantId}.plan_data");
        } catch (\BadMethodCallException) {
            // Cache driver does not support tags — the entry will simply be
            // recomputed on the next request.
        }
    }
}
