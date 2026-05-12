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

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only(['id', 'name', 'email']),
            ],
            'plan' => $currentTenant && $currentTenant instanceof Tenant ? [
                'name' => $currentTenant->plan?->name,
                'features' => $currentTenant->plan?->features ?? [],
                'limits' => [
                    'users' => $currentTenant->getLimit('users'),
                    'storage' => $currentTenant->getLimit('storage'),
                ],
            ] : null,
        ];
    }
}
