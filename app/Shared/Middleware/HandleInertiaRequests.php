<?php

namespace App\Shared\Middleware;

use App\Models\Tenant;
use App\Plans\Support\FeatureFlagCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

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

    public function share(Request $request): array
    {
        $currentTenant = tenant();

        $planData = null;
        if ($currentTenant && $currentTenant instanceof Tenant) {
            $tenantId = $currentTenant->id;

            $planData = $this->cachedPlanData($currentTenant, $tenantId);
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only(['id', 'name', 'email']),
            ],
            'feature_definitions' => FeatureFlagCatalog::definitions(),
            'plan' => $planData,
        ];
    }
}
