<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Billing\Factories\ResourceEnforcementFactory;
use App\Billing\Strategies\EnterpriseResourceStrategy;
use App\Billing\Strategies\GrowthResourceStrategy;
use App\Billing\Strategies\ProResourceStrategy;
use App\Billing\Strategies\StarterResourceStrategy;
use App\Models\Plan;
use App\Models\Tenant;
use App\Shared\Contracts\ResourceEnforcementInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceEnforcementFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_from_plan_returns_pro_strategy_for_pro_slug(): void
    {
        $plan = Plan::factory()->make(['slug' => 'pro']);

        $strategy = ResourceEnforcementFactory::fromPlan($plan);

        $this->assertInstanceOf(ProResourceStrategy::class, $strategy);
        $this->assertInstanceOf(ResourceEnforcementInterface::class, $strategy);
    }

    public function test_from_plan_returns_growth_strategy_for_growth_slug(): void
    {
        $plan = Plan::factory()->make(['slug' => 'growth']);

        $strategy = ResourceEnforcementFactory::fromPlan($plan);

        $this->assertInstanceOf(GrowthResourceStrategy::class, $strategy);
    }

    public function test_from_plan_returns_enterprise_strategy_for_enterprise_slug(): void
    {
        $plan = Plan::factory()->make(['slug' => 'enterprise']);

        $strategy = ResourceEnforcementFactory::fromPlan($plan);

        $this->assertInstanceOf(EnterpriseResourceStrategy::class, $strategy);
    }

    public function test_from_plan_returns_starter_strategy_for_unknown_slug(): void
    {
        $plan = Plan::factory()->make(['slug' => 'custom']);

        $strategy = ResourceEnforcementFactory::fromPlan($plan);

        $this->assertInstanceOf(StarterResourceStrategy::class, $strategy);
    }

    public function test_make_resolves_plan_from_tenant(): void
    {
        $plan = Plan::factory()->create(['slug' => 'pro']);
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $tenant->setRelation('plan', $plan);

        $strategy = ResourceEnforcementFactory::make($tenant);

        $this->assertInstanceOf(ProResourceStrategy::class, $strategy);
    }
}
