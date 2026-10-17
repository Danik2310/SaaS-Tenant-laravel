<?php

declare(strict_types=1);

namespace Tests\Feature\Plans;

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Tenant;
use App\Plans\Support\TenantPlanCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Editing a plan (or renaming a feature flag that cascades onto plans) must
 * invalidate the cached `plan_data` Inertia prop of exactly the affected
 * tenants — no stale reads for them, no needless invalidation for the rest.
 */
class TenantPlanCacheTest extends TestCase
{
    public function test_forget_for_plan_clears_only_tenants_on_that_plan(): void
    {
        $editedPlan = Plan::factory()->create();
        $otherPlan = Plan::factory()->create();

        $onEditedPlan = $this->createTenant('test-plan-cache-edited', (int) $editedPlan->id);
        $onOtherPlan = $this->createTenant('test-plan-cache-other', (int) $otherPlan->id);

        $this->putPlanData($onEditedPlan);
        $this->putPlanData($onOtherPlan);

        TenantPlanCache::forgetForPlan((int) $editedPlan->id);

        $this->assertFalse(
            $this->hasPlanData($onEditedPlan),
            'tenants on the edited plan must be refreshed'
        );
        $this->assertTrue(
            $this->hasPlanData($onOtherPlan),
            'tenants on other plans keep their cached plan data'
        );
    }

    public function test_forget_for_feature_clears_only_tenants_whose_plan_gates_that_feature(): void
    {
        $gatedPlan = Plan::factory()->create();
        $ungatedPlan = Plan::factory()->create();

        PlanFeature::create([
            'plan_id' => $gatedPlan->id,
            'feature_key' => 'advanced_reporting',
            'is_enabled' => true,
        ]);

        $onGatedPlan = $this->createTenant('test-plan-cache-gated', (int) $gatedPlan->id);
        $onUngatedPlan = $this->createTenant('test-plan-cache-ungated', (int) $ungatedPlan->id);

        $this->putPlanData($onGatedPlan);
        $this->putPlanData($onUngatedPlan);

        TenantPlanCache::forgetForFeature('advanced_reporting');

        $this->assertFalse(
            $this->hasPlanData($onGatedPlan),
            'tenants whose plan gates the renamed feature must be refreshed'
        );
        $this->assertTrue(
            $this->hasPlanData($onUngatedPlan),
            'tenants without that feature keep their cached plan data'
        );
    }

    public function test_forget_for_feature_ignores_a_feature_key_no_plan_gates(): void
    {
        $plan = Plan::factory()->create();
        $tenant = $this->createTenant('test-plan-cache-nofeature', (int) $plan->id);

        $this->putPlanData($tenant);

        TenantPlanCache::forgetForFeature('nonexistent_feature_key');

        $this->assertTrue($this->hasPlanData($tenant));
    }

    private function createTenant(string $id, int $planId): Tenant
    {
        return Tenant::withoutEvents(fn (): Tenant => Tenant::create([
            'id' => $id,
            'plan_id' => $planId,
        ]));
    }

    private function putPlanData(Tenant $tenant): void
    {
        Cache::tags([$this->tagFor($tenant)])
            ->put($this->keyFor($tenant), ['name' => 'cached plan data']);
    }

    private function hasPlanData(Tenant $tenant): bool
    {
        return Cache::tags([$this->tagFor($tenant)])->has($this->keyFor($tenant));
    }

    private function tagFor(Tenant $tenant): string
    {
        return 'tenant_'.$tenant->id;
    }

    private function keyFor(Tenant $tenant): string
    {
        return 'tenant.'.$tenant->id.'.plan_data';
    }
}
