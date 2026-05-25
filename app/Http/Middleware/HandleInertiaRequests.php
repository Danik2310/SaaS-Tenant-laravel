<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Illuminate\Http\Request;
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
            $currentTenant->load('plan');
            $plan = $currentTenant->plan;

            $planData = [
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
