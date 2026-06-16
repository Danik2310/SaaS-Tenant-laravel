<?php

namespace Tests\Feature\Admin;

use App\Factories\ResourceEnforcementFactory;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Strategies\EnterpriseResourceStrategy;
use App\Services\Strategies\GrowthResourceStrategy;
use App\Services\Strategies\ProResourceStrategy;
use App\Services\Strategies\StarterResourceStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanGatingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_enforcement_factory_returns_starter_for_free_plan(): void
    {
        $plan = Plan::factory()->create(['slug' => 'free', 'max_users' => 5]);
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        $strategy = ResourceEnforcementFactory::make($tenant);

        $this->assertInstanceOf(StarterResourceStrategy::class, $strategy);
    }

    public function test_resource_enforcement_factory_returns_growth_for_growth_plan(): void
    {
        $plan = Plan::factory()->create(['slug' => 'growth', 'max_users' => 20]);
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        $strategy = ResourceEnforcementFactory::make($tenant);

        $this->assertInstanceOf(GrowthResourceStrategy::class, $strategy);
    }

    public function test_resource_enforcement_factory_returns_pro_for_pro_plan(): void
    {
        $plan = Plan::factory()->create(['slug' => 'pro', 'max_users' => 50]);
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        $strategy = ResourceEnforcementFactory::make($tenant);

        $this->assertInstanceOf(ProResourceStrategy::class, $strategy);
    }

    public function test_resource_enforcement_factory_returns_enterprise_for_enterprise_plan(): void
    {
        $plan = Plan::factory()->create(['slug' => 'enterprise', 'max_users' => 999]);
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        $strategy = ResourceEnforcementFactory::make($tenant);

        $this->assertInstanceOf(EnterpriseResourceStrategy::class, $strategy);
    }

    public function test_growth_resource_strategy_has_correct_default_limits(): void
    {
        $plan = Plan::factory()->create(['slug' => 'growth']);
        $strategy = new GrowthResourceStrategy($plan);

        $this->assertEquals(20, $strategy->maxUsers());
        $this->assertEquals(500, $strategy->maxStorageMb());
        $this->assertEquals(3, $strategy->maxWarehouses());
        $this->assertEquals(25, $strategy->maxCategories());
        $this->assertEquals(250, $strategy->maxProducts());
        $this->assertEquals(['csv', 'xlsx'], $strategy->allowedExportFormats());
        $this->assertTrue($strategy->hasFeature('basic_reports'));
        $this->assertTrue($strategy->hasFeature('advanced_reports'));
        $this->assertTrue($strategy->hasFeature('bulk_operations'));
    }

    public function test_growth_resource_strategy_uses_plan_values_when_set(): void
    {
        $plan = Plan::factory()->create([
            'slug' => 'growth',
            'max_users' => 30,
            'max_storage' => 1000,
            'max_warehouses' => 5,
            'max_categories' => 50,
            'max_products' => 500,
        ]);
        $strategy = new GrowthResourceStrategy($plan);

        $this->assertEquals(30, $strategy->maxUsers());
        $this->assertEquals(1000, $strategy->maxStorageMb());
        $this->assertEquals(5, $strategy->maxWarehouses());
        $this->assertEquals(50, $strategy->maxCategories());
        $this->assertEquals(500, $strategy->maxProducts());
    }

    public function test_plan_has_feature_returns_true_for_existing_growth_feature(): void
    {
        $plan = Plan::factory()->create(['slug' => 'growth']);
        $plan->featureGates()->createMany([
            ['feature_key' => 'basic_reports', 'is_enabled' => true],
            ['feature_key' => 'advanced_reports', 'is_enabled' => true],
        ]);

        $this->assertTrue($plan->hasFeature('basic_reports'));
        $this->assertTrue($plan->hasFeature('advanced_reports'));
        $this->assertFalse($plan->hasFeature('white_label'));
    }
}
