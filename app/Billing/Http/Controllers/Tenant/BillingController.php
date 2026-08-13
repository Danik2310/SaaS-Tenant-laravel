<?php

declare(strict_types=1);

namespace App\Billing\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Models\Tenant;

/**
 * @group Tenant Billing
 *
 * APIs for tenant billing and subscription management.
 */
class BillingController extends Controller
{
    /**
     * Show upgrade page.
     *
     * Returns the plans available for the tenant to upgrade to.
     *
     * @authenticated
     *
     * @responseField currentPlan object|null The tenant's current plan.
     * @responseField plans array List of available plans with feature gates.
     */
    public function upgrade()
    {
        $tenant = tenant();

        $plans = Plan::with('featureGates')
            ->where('slug', '!=', 'trial')
            ->orderBy('price')
            ->orderBy('name')
            ->paginate(5);

        return inertia('Tenant/Billing/Upgrade', [
            'currentPlan' => $tenant instanceof Tenant ? $tenant->plan : null,
            'plans' => PlanResource::collection($plans),
        ]);
    }
}
