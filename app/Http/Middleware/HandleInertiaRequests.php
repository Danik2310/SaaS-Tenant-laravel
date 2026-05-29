<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
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

    public function share(Request $request): array
    {
        $currentTenant = tenant();

        $planData = null;
        if ($currentTenant && $currentTenant instanceof Tenant) {
            $tenantId = $currentTenant->id;

            $planData = Cache::tags(['tenant_'.$tenantId])->remember("tenant.{$tenantId}.plan_data", 3600, function () use ($currentTenant) {
                $currentTenant->load('plan');
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
            });
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only(['id', 'name', 'email']),
            ],
            'plan' => $planData,
        ];
    }
}
