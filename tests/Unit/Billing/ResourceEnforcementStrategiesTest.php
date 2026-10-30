<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Billing\Strategies\EnterpriseResourceStrategy;
use App\Billing\Strategies\GrowthResourceStrategy;
use App\Billing\Strategies\ProResourceStrategy;
use App\Billing\Strategies\StarterResourceStrategy;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Shared\Contracts\ResourceEnforcementInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceEnforcementStrategiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_strategy_defaults_to_zero_and_no_features(): void
    {
        $plan = Plan::factory()->create([
            'max_users' => 1,
            'max_storage' => 512,
            'max_warehouses' => 1,
            'max_categories' => 5,
            'max_products' => 10,
        ]);
        // No feature gates → hasFeature returns false for everything.
        $plan->setRelation('featureGates', collect());

        $strategy = app(StarterResourceStrategy::class, ['plan' => $plan]);

        $this->assertInstanceOf(ResourceEnforcementInterface::class, $strategy);
        $this->assertSame(1, $strategy->maxUsers());
        $this->assertSame(512, $strategy->maxStorageMb());
        $this->assertSame(1, $strategy->maxWarehouses());
        $this->assertSame(5, $strategy->maxCategories());
        $this->assertSame(10, $strategy->maxProducts());
        $this->assertSame(['csv'], $strategy->allowedExportFormats());
        $this->assertFalse($strategy->hasFeature('api_access'));
    }

    public function test_pro_strategy_returns_larger_limits(): void
    {
        $plan = Plan::factory()->create([
            'max_users' => 25,
            'max_storage' => 10240,
            'max_warehouses' => 5,
            'max_categories' => 50,
            'max_products' => 500,
        ]);
        $plan->setRelation('featureGates', collect([
            new PlanFeature(['feature_key' => 'api_access', 'is_enabled' => true]),
        ]));

        $strategy = app(ProResourceStrategy::class, ['plan' => $plan]);

        $this->assertSame(25, $strategy->maxUsers());
        $this->assertSame(10240, $strategy->maxStorageMb());
        $this->assertSame(5, $strategy->maxWarehouses());
        $this->assertSame(50, $strategy->maxCategories());
        $this->assertSame(500, $strategy->maxProducts());
        $this->assertSame(['csv', 'xlsx', 'pdf'], $strategy->allowedExportFormats());
        $this->assertTrue($strategy->hasFeature('api_access'));
    }

    public function test_growth_strategy_limits(): void
    {
        $plan = Plan::factory()->create([
            'max_users' => 50,
            'max_storage' => 51200,
            'max_warehouses' => 10,
            'max_categories' => 100,
            'max_products' => 2000,
        ]);
        $plan->setRelation('featureGates', collect());

        $strategy = new GrowthResourceStrategy($plan);

        $this->assertSame(50, $strategy->maxUsers());
        $this->assertSame(51200, $strategy->maxStorageMb());
        $this->assertSame(10, $strategy->maxWarehouses());
        $this->assertSame(100, $strategy->maxCategories());
        $this->assertSame(2000, $strategy->maxProducts());
    }

    public function test_enterprise_strategy_uses_infinity_when_unlimited(): void
    {
        $plan = Plan::factory()->create([
            'max_users' => null,
            'max_storage' => null,
            'max_warehouses' => null,
            'max_categories' => null,
            'max_products' => null,
        ]);
        $plan->setRelation('featureGates', collect([
            new PlanFeature(['feature_key' => 'white_label', 'is_enabled' => true]),
        ]));

        $strategy = new EnterpriseResourceStrategy($plan);

        $this->assertSame(PHP_INT_MAX, $strategy->maxUsers());
        $this->assertSame(PHP_INT_MAX, $strategy->maxStorageMb());
        $this->assertSame(PHP_INT_MAX, $strategy->maxWarehouses());
        $this->assertSame(PHP_INT_MAX, $strategy->maxCategories());
        $this->assertSame(PHP_INT_MAX, $strategy->maxProducts());
        $this->assertTrue($strategy->hasFeature('white_label'));
    }
}
