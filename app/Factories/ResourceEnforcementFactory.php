<?php

declare(strict_types=1);

namespace App\Factories;

use App\Contracts\ResourceEnforcementInterface;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Strategies\EnterpriseResourceStrategy;
use App\Services\Strategies\ProResourceStrategy;
use App\Services\Strategies\StarterResourceStrategy;

class ResourceEnforcementFactory
{
    public static function make(Tenant $tenant): ResourceEnforcementInterface
    {
        $plan = $tenant->plan ?? Plan::where('slug', 'starter')->firstOrFail();

        return self::fromPlan($plan);
    }

    public static function fromPlan(Plan $plan): ResourceEnforcementInterface
    {
        return match ($plan->slug) {
            'pro' => app(ProResourceStrategy::class, ['plan' => $plan]),
            'enterprise' => app(EnterpriseResourceStrategy::class, ['plan' => $plan]),
            default => app(StarterResourceStrategy::class, ['plan' => $plan]),
        };
    }
}
