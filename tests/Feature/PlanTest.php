<?php

namespace Tests\Feature;

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
            'max_users' => 5,
            'features' => 'Feature 1, Feature 2',
        ];

        $response = $this->postJson('/admin/api/plans', $data);

        $response->assertStatus(201)
            ->assertJsonStructure(['plan' => ['id', 'name', 'slug', 'price', 'features']]);

        $this->assertDatabaseHas('plans', [
            'name' => 'Basic Plan',
            'slug' => 'basic',
            'price' => 9.99,
            'max_users' => 5,
        ]);

        // Check features are stored as array
        $plan = Plan::first();
        $this->assertEquals(['Feature 1', 'Feature 2'], $plan->features);
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
            'features' => ['Old Feature'],
        ]);

        $updateData = [
            'name' => 'Updated Plan',
            'slug' => 'updated',
            'price' => 15.99,
            'max_users' => 10,
            'features' => 'New Feature 1, New Feature 2',
        ];

        $response = $this->putJson("/admin/api/plans/{$plan->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure(['plan' => ['id', 'name', 'slug', 'price', 'features']]);

        $this->assertDatabaseHas('plans', [
            'name' => 'Updated Plan',
            'slug' => 'updated',
            'price' => 15.99,
            'max_users' => 10,
        ]);

        $updatedPlan = Plan::find($plan->id);
        $this->assertEquals(['New Feature 1', 'New Feature 2'], $updatedPlan->features);
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

        $this->assertCount(3, $response->json('plans'));
    }
}
