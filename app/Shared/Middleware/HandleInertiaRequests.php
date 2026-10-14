<?php

namespace App\Shared\Middleware;

use App\Models\Tenant;
use App\Plans\Support\FeatureFlagCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

/**
 * Shares app-wide props with every Inertia response.
 *
 * Flow (`share()`):
 *  1. Resolve the current tenant and, when present, its plan data — plan, feature
 *     list, resource limits and trial flags — cached per tenant with a fallback
 *     for non-taggable cache stores.
 *  2. Expose the authenticated user, or a synthetic identity while impersonating.
 *  3. Expose impersonation state, feature definitions and the tenant domain
 *     suffix.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * Asset version string used by Inertia for cache-busting.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Retrieve plan data from cache with a fallback for non-taggable cache stores.
     */
    private function cachedPlanData(Tenant $currentTenant, string $tenantId): ?array
    {
        $computePlanData = function () use ($currentTenant) {
            $currentTenant->load('plan.featureGates');
            $plan = $currentTenant->plan;

            return [
                'id' => $plan?->id,
                'name' => $plan?->name,
                'slug' => $plan?->slug,
                'price' => $plan?->price,
                'features' => $plan?->features ?? [],
                'limits' => [
                    'users' => $currentTenant->getLimit('users'),
                    'storage' => $currentTenant->getLimit('storage'),
                    'warehouses' => $currentTenant->getLimit('warehouses'),
                    'categories' => $currentTenant->getLimit('categories'),
                    'products' => $currentTenant->getLimit('products'),
                ],
                'is_on_trial' => $currentTenant->isOnTrial(),
                'trial_has_expired' => $currentTenant->trialHasExpired(),
            ];
        };

        try {
            return Cache::tags(['tenant_'.$tenantId])->remember("tenant.{$tenantId}.plan_data", 3600, $computePlanData);
        } catch (\BadMethodCallException) {
            return $computePlanData();
        }
    }

    /**
     * Build the shared prop set for the current request.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $currentTenant = tenant();

        $planData = null;
        if ($currentTenant && $currentTenant instanceof Tenant) {
            $tenantId = $currentTenant->id;

            $planData = $this->cachedPlanData($currentTenant, $tenantId);
        }

        $impersonation = session('impersonation');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $impersonation
                    ? ['id' => null, 'name' => $impersonation['admin_name'] ?? 'Administrator', 'email' => $impersonation['admin_email'] ?? '']
                    : $request->user()?->only(['id', 'name', 'email']),
            ],
            'impersonation' => $impersonation && $currentTenant instanceof Tenant ? [
                'active' => true,
                'tenant_name' => $currentTenant->name,
                'tenant_id' => $currentTenant->id,
                'admin_name' => $impersonation['admin_name'] ?? 'Administrator',
                'read_only' => (bool) config('impersonation.read_only', true),
                'started_at' => $impersonation['started_at'] ?? null,
            ] : ['active' => false],
            'feature_definitions' => FeatureFlagCatalog::definitions(),
            'plan' => $planData,
            'tenant_domain_suffix' => (string) config('tenancy.tenant_domain_suffix', 'sasapp'),
        ];
    }
}
