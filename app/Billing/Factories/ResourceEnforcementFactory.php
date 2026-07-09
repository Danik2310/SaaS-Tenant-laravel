<?php

declare(strict_types=1);

namespace App\Billing\Factories;

use App\Billing\Contracts\ResourceEnforcementInterface;
use App\Billing\Strategies\EnterpriseResourceStrategy;
use App\Billing\Strategies\GrowthResourceStrategy;
use App\Billing\Strategies\ProResourceStrategy;
use App\Billing\Strategies\StarterResourceStrategy;
use App\Models\Plan;
use App\Models\Tenant;

class ResourceEnforcementFactory
{
    public static function make(Tenant $tenant): ResourceEnforcementInterface
    {
        $defaultSlug = config('tenancy.default_plan_slug', 'free');
        $plan = $tenant->plan ?? Plan::where('slug', $defaultSlug)->firstOrFail();

        return self::fromPlan($plan);
    }

    public static function fromPlan(Plan $plan): ResourceEnforcementInterface
    {
        return match ($plan->slug) {
            'pro' => app(ProResourceStrategy::class, ['plan' => $plan]),
            'growth' => app(GrowthResourceStrategy::class, ['plan' => $plan]),
            'enterprise' => app(EnterpriseResourceStrategy::class, ['plan' => $plan]),
            default => app(StarterResourceStrategy::class, ['plan' => $plan]),
        };
    }
}
