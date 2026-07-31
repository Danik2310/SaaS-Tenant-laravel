<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();
    }

    /**
     * 🧪 Test: Can create a plan
     */
    public function test_can_create_plan()
    {
        $data = [
            'name' => 'Basic Plan',
            'slug' => 'basic',
            'price' => 9.99,
            'status' => 'active',
            'duration_months' => 12,
            'max_users' => 5,
            'features' => ['api_access', 'custom_domain'],
        ];

        $response = $this->postJson('/admin/api/plans', $data);

        $response->assertStatus(201)
            ->assertJsonStructure(['plan' => ['id', 'name', 'slug', 'price', 'features']]);

        $plan = Plan::where('slug', 'basic')->first();
        $this->assertNotNull($plan);
        $this->assertSame('Basic Plan', $plan->name);
        $this->assertSame(9.99, (float) $plan->price);
        $this->assertSame(5, $plan->max_users);
        $this->assertEquals(['api_access', 'custom_domain'], $plan->features);
    }

    /**
     * 🧪 Test: Can create a plan with comma-separated feature string
     */
    public function test_can_create_plan_with_comma_separated_features()
    {
        $data = [
            'name' => 'Legacy Plan',
            'slug' => 'legacy',
            'price' => 4.99,
            'status' => 'active',
            'duration_months' => 12,
            'features' => 'api_access, custom_domain',
        ];

        $response = $this->postJson('/admin/api/plans', $data);

        $response->assertStatus(201);

        $plan = Plan::where('slug', 'legacy')->first();
        $this->assertNotNull($plan);
        $this->assertEquals(['api_access', 'custom_domain'], $plan->features);
    }

    /**
     * 🧪 Test: Create plan rejects unknown feature flags
     */
    public function test_create_plan_rejects_unknown_feature_flags()
    {
        $data = [
            'name' => 'Bad Plan',
            'slug' => 'bad',
            'price' => 1,
            'features' => ['api_access', 'mystery_flag'],
        ];

        $response = $this->postJson('/admin/api/plans', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['features']);
    }

    /**
     * 🧪 Test: Update plan rejects unknown feature flags
     */
    public function test_update_plan_rejects_unknown_feature_flags()
    {
        $plan = Plan::factory()->create(['name' => 'Reject Plan', 'slug' => 'reject-old', 'price' => 5.00]);

        $response = $this->putJson("/admin/api/plans/{$plan->id}", [
            'name' => 'Updated Plan',
            'slug' => 'updated',
            'price' => 15.99,
            'features' => 'all',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['features']);
    }

    /**
     * 🧪 Test: Can update a plan
     */
    public function test_can_update_plan()
    {
        $plan = Plan::factory()->create([
            'name' => 'Old Plan',
            'slug' => 'old',
            'price' => 5.00,
        ]);

        $plan->featureGates()->create([
            'feature_key' => 'api_access',
            'is_enabled' => true,
        ]);

        $updateData = [
            'name' => 'Updated Plan',
            'slug' => 'updated',
            'price' => 15.99,
            'max_users' => 10,
            'features' => 'advanced, api_access',
        ];

        $response = $this->putJson("/admin/api/plans/{$plan->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure(['plan' => ['id', 'name', 'slug', 'price', 'features']]);

        $updatedPlan = Plan::find($plan->id);
        $this->assertSame('Updated Plan', $updatedPlan->name);
        $this->assertSame(15.99, (float) $updatedPlan->price);
        $this->assertSame(10, $updatedPlan->max_users);
        $this->assertEquals(['advanced', 'api_access'], $updatedPlan->features);
    }

    /**
     * 🧪 Test: Validation fails for invalid data
     */
    public function test_validation_fails_for_invalid_data()
    {
        $invalidData = [
            'name' => '',
            'slug' => '',
            'price' => -10,
        ];

        $response = $this->postJson('/admin/api/plans', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'slug', 'price']);
    }

    /**
     * 🧪 Test: Can list plans
     */
    public function test_can_list_plans()
    {
        Plan::factory()->count(3)->create();

        $response = $this->getJson('/admin/api/plans');

        $response->assertStatus(200)
            ->assertJsonStructure(['plans' => [
                '*' => ['id', 'name', 'slug', 'price'],
            ]]);

        $this->assertGreaterThanOrEqual(3, count($response->json('plans')));
    }

    /**
     * 🧪 Test: Returns 404 for non-existent plan
     */
    public function test_returns_404_for_nonexistent_plan()
    {
        $this->getJson('/admin/api/plans/99999')->assertStatus(404);
        $this->putJson('/admin/api/plans/99999', [
            'name' => 'Ghost', 'slug' => 'ghost', 'price' => 0, 'features' => 'api_access',
        ])->assertStatus(404);
    }

    /**
     * 🧪 Test: Users without permission cannot manage plans
     */
    public function test_unauthorized_user_cannot_manage_plans()
    {
        $admin = AdminUser::factory()->create();
        $this->actingAs($admin, 'admin');

        $this->getJson('/admin/api/plans')->assertStatus(403);
        $this->postJson('/admin/api/plans', [
            'name' => 'Test', 'slug' => 'test', 'price' => 0, 'features' => 'api_access',
        ])->assertStatus(403);
    }

    /**
     * 🧪 Test: Guest is redirected to login
     */
    public function test_guest_cannot_access_plans()
    {
        auth('admin')->logout();
        $this->getJson('/admin/api/plans')->assertStatus(401);
    }
}
