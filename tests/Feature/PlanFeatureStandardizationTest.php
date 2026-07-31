<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Plans\Support\PlanFeatureNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanFeatureStandardizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_all_sentinel_is_expanded_to_all_canonical_keys()
    {
        $plan = Plan::factory()->create();
        $plan->featureGates()->create(['feature_key' => 'all', 'is_enabled' => true]);

        PlanFeatureNormalizer::normalize();

        $expected = array_keys(config('plan_features'));
        sort($expected);

        $actual = $plan->fresh()->features;
        sort($actual);

        $this->assertSame($expected, $actual);
        $this->assertFalse($plan->fresh()->hasFeature('all'));
    }

    public function test_legacy_basic_flag_is_mapped_to_basic_reports()
    {
        $plan = Plan::factory()->create();
        $plan->featureGates()->create(['feature_key' => 'basic', 'is_enabled' => true]);

        PlanFeatureNormalizer::normalize();

        $fresh = $plan->fresh();
        $this->assertTrue($fresh->hasFeature('basic_reports'));
        $this->assertFalse($fresh->hasFeature('basic'));
    }

    public function test_unknown_feature_keys_are_removed()
    {
        $plan = Plan::factory()->create();
        $plan->featureGates()->create(['feature_key' => 'mystery_flag', 'is_enabled' => true]);

        PlanFeatureNormalizer::normalize();

        $this->assertFalse($plan->fresh()->hasFeature('mystery_flag'));
    }
}
