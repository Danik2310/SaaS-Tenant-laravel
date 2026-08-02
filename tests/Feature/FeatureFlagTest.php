<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\FeatureFlag;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();
    }

    private function createFlag(array $overrides = []): FeatureFlag
    {
        return FeatureFlag::create(array_merge([
            'key' => 'flag_'.substr(uniqid(), -8),
            'label' => 'Test Flag',
            'is_locked' => false,
            'is_active' => true,
            'sort_order' => 0,
        ], $overrides));
    }

    /**
     * 🧪 Test: The canonical flags are seeded and locked.
     */
    public function test_catalog_seeded_with_locked_canonical_flags()
    {
        foreach (array_keys(config('plan_features')) as $key) {
            $flag = FeatureFlag::where('key', $key)->first();
            $this->assertNotNull($flag, "Flag {$key} should exist");
            $this->assertTrue($flag->is_locked, "Flag {$key} should be locked");
        }
    }

    /**
     * 🧪 Test: Can list feature flags.
     */
    public function test_can_list_feature_flags()
    {
        $response = $this->getJson('/admin/api/feature-flags');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'flags' => [
                    '*' => ['id', 'key', 'label', 'description', 'is_locked', 'is_active', 'sort_order'],
                ],
                'meta' => ['total'],
            ]);

        $this->assertGreaterThanOrEqual(8, $response->json('meta.total'));
    }

    /**
     * 🧪 Test: Can create a feature flag.
     */
    public function test_can_create_feature_flag()
    {
        $key = 'cargo_'.substr(uniqid(), -8);

        $response = $this->postJson('/admin/api/feature-flags', [
            'key' => $key,
            'label' => 'Cargo Tracking',
            'description' => 'Track shipments in transit.',
            'is_active' => true,
            'sort_order' => 5,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['flag' => ['id', 'key', 'label']]);

        $flag = FeatureFlag::where('key', $key)->first();
        $this->assertNotNull($flag);
        $this->assertSame('Cargo Tracking', $flag->label);
        $this->assertFalse($flag->is_locked);
        $this->assertTrue($flag->is_active);
    }

    /**
     * 🧪 Test: Create auto-assigns the next sort order when omitted.
     */
    public function test_create_auto_assigns_sort_order_when_omitted()
    {
        $max = (int) FeatureFlag::max('sort_order');

        $this->postJson('/admin/api/feature-flags', [
            'key' => 'auto_'.substr(uniqid(), -8),
            'label' => 'Auto Ordered',
        ])->assertStatus(201);

        $this->assertSame($max + 1, (int) FeatureFlag::max('sort_order'));
    }

    /**
     * 🧪 Test: Create rejects a duplicate key.
     */
    public function test_create_rejects_duplicate_key()
    {
        $this->postJson('/admin/api/feature-flags', [
            'key' => 'advanced',
            'label' => 'Duplicate',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['key']);
    }

    /**
     * 🧪 Test: Create rejects an invalid key format.
     */
    public function test_create_rejects_invalid_key_format()
    {
        $this->postJson('/admin/api/feature-flags', [
            'key' => 'Bad Key!',
            'label' => 'Invalid',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['key']);
    }

    /**
     * 🧪 Test: Label of a locked flag can be edited.
     */
    public function test_can_update_label_of_locked_flag()
    {
        $flag = FeatureFlag::where('key', 'advanced')->firstOrFail();

        $response = $this->putJson("/admin/api/feature-flags/{$flag->id}", [
            'key' => 'advanced',
            'label' => 'Advanced Suite',
            'description' => 'Updated description',
        ]);

        $response->assertStatus(200);

        $this->assertSame('Advanced Suite', $flag->fresh()->label);
        $this->assertSame('advanced', $flag->fresh()->key);
    }

    /**
     * 🧪 Test: Renaming a locked flag is rejected.
     */
    public function test_cannot_rename_locked_flag()
    {
        $flag = FeatureFlag::where('key', 'advanced')->firstOrFail();

        $this->putJson("/admin/api/feature-flags/{$flag->id}", [
            'key' => 'advanced_v2',
            'label' => 'Advanced Suite',
        ])->assertStatus(422)
            ->assertJson(['message' => 'This flag is locked and its key cannot be changed.']);
    }

    /**
     * 🧪 Test: Deleting a locked flag is rejected.
     */
    public function test_cannot_delete_locked_flag()
    {
        $flag = FeatureFlag::where('key', 'advanced')->firstOrFail();

        $this->deleteJson("/admin/api/feature-flags/{$flag->id}")->assertStatus(422);

        $this->assertNotNull(FeatureFlag::where('key', 'advanced')->first());
    }

    /**
     * 🧪 Test: Renaming an unlocked flag cascades to plan feature gates.
     */
    public function test_can_rename_unlocked_flag_and_cascade_to_plan_gates()
    {
        $flag = $this->createFlag(['key' => 'cargo_tracking', 'label' => 'Cargo Tracking']);
        $plan = Plan::factory()->create(['slug' => 'cascade-'.uniqid()]);
        PlanFeature::create([
            'plan_id' => $plan->id,
            'feature_key' => 'cargo_tracking',
            'is_enabled' => true,
        ]);

        $response = $this->putJson("/admin/api/feature-flags/{$flag->id}", [
            'key' => 'cargo_tracking_v2',
            'label' => 'Cargo Tracking',
        ]);

        $response->assertStatus(200);

        $this->assertSame('cargo_tracking_v2', $flag->fresh()->key);
        $this->assertNull(PlanFeature::where('plan_id', $plan->id)->where('feature_key', 'cargo_tracking')->first());
        $this->assertNotNull(PlanFeature::where('plan_id', $plan->id)->where('feature_key', 'cargo_tracking_v2')->first());
        $this->assertSame(['cargo_tracking_v2'], $plan->fresh()->features);
    }

    /**
     * 🧪 Test: Deleting a flag that plans use is rejected.
     */
    public function test_cannot_delete_flag_in_use_by_plan()
    {
        $flag = $this->createFlag(['key' => 'in_use_flag', 'label' => 'In Use']);
        $plan = Plan::factory()->create(['slug' => 'inuse-'.uniqid()]);
        PlanFeature::create([
            'plan_id' => $plan->id,
            'feature_key' => 'in_use_flag',
            'is_enabled' => true,
        ]);

        $this->deleteJson("/admin/api/feature-flags/{$flag->id}")
            ->assertStatus(422)
            ->assertJson(['message' => 'This flag is assigned to 1 plan(s). Remove it from those plans first.']);

        $this->assertNotNull(FeatureFlag::where('key', 'in_use_flag')->first());
    }

    /**
     * 🧪 Test: An unused, unlocked flag can be deleted.
     */
    public function test_can_delete_unused_unlocked_flag()
    {
        $flag = $this->createFlag(['key' => 'unused_flag', 'label' => 'Unused']);

        $this->deleteJson("/admin/api/feature-flags/{$flag->id}")->assertStatus(204);

        $this->assertNull(FeatureFlag::where('key', 'unused_flag')->first());
    }

    /**
     * 🧪 Test: Plan validation reads the database catalog.
     */
    public function test_plan_validation_uses_catalog()
    {
        $customKey = 'custom_'.substr(uniqid(), -8);
        $this->createFlag(['key' => $customKey, 'label' => 'Custom']);

        $response = $this->postJson('/admin/api/plans', [
            'name' => 'Catalog Plan',
            'slug' => 'catalog-'.uniqid(),
            'price' => 19.99,
            'status' => 'active',
            'duration_months' => 12,
            'features' => [$customKey, 'advanced'],
        ]);

        $response->assertStatus(201);
    }

    /**
     * 🧪 Test: Users without permission cannot manage feature flags.
     */
    public function test_unauthorized_user_cannot_manage_feature_flags()
    {
        $admin = AdminUser::factory()->create();
        $this->actingAs($admin, 'admin');

        $this->getJson('/admin/api/feature-flags')->assertStatus(403);
        $this->postJson('/admin/api/feature-flags', [
            'key' => 'nope', 'label' => 'Nope',
        ])->assertStatus(403);
    }

    /**
     * 🧪 Test: Guest is redirected to login.
     */
    public function test_guest_cannot_access_feature_flags()
    {
        auth('admin')->logout();

        $this->getJson('/admin/api/feature-flags')->assertStatus(401);
    }
}
