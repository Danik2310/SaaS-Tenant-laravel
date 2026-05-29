<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class PaymentMethodCrudTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();
    }

    public function test_can_create_payment_method(): void
    {
        $data = [
            'name' => 'Stripe Integration Test',
            'provider' => 'stripe',
            'api_key' => 'pk_test_123456789012345678901234567890',
            'secret_key' => 'sk_live_123456789012345678901234567890',
            'mode' => 'test',
            'active' => true,
        ];

        $response = $this->postJson('/admin/api/payment-methods', $data);
        $response->assertStatus(201);

        $this->assertDatabaseHas('payment_methods', [
            'name' => 'Stripe Integration Test',
            'provider' => 'stripe',
        ]);
    }

    public function test_can_list_payment_methods(): void
    {
        PaymentMethod::factory()->count(2)->create();

        $response = $this->getJson('/admin/api/payment-methods');
        $response->assertStatus(200);
        $this->assertCount(2, $response->json()['methods']);
    }

    public function test_can_show_payment_method(): void
    {
        $method = PaymentMethod::factory()->create(['name' => 'Test Show']);

        $response = $this->getJson("/admin/api/payment-methods/{$method->id}");
        $response->assertStatus(200);
        $this->assertEquals('Test Show', $response->json()['method']['name']);
    }

    public function test_can_update_payment_method(): void
    {
        $method = PaymentMethod::factory()->create(['name' => 'Old Name']);

        $updateData = [
            'name' => 'Updated Name',
            'provider' => 'stripe',
            'api_key' => 'pk_test_updated_123456789012345678901234567890',
            'secret_key' => 'sk_live_updated_123456789012345678901234567890',
            'mode' => 'live',
            'active' => false,
        ];

        $response = $this->putJson("/admin/api/payment-methods/{$method->id}", $updateData);
        $response->assertStatus(200);

        $this->assertDatabaseHas('payment_methods', [
            'id' => $method->id,
            'name' => 'Updated Name',
            'mode' => 'live',
        ]);
    }

    public function test_can_delete_payment_method(): void
    {
        $method = PaymentMethod::factory()->create();

        $response = $this->deleteJson("/admin/api/payment-methods/{$method->id}");
        $response->assertStatus(204);

        $this->assertDatabaseMissing('payment_methods', ['id' => $method->id]);
    }

    public function test_returns_404_for_nonexistent_method(): void
    {
        $this->getJson('/admin/api/payment-methods/99999')->assertStatus(404);
        $this->putJson('/admin/api/payment-methods/99999', [
            'name' => 'Nope', 'provider' => 'stripe',
            'api_key' => 'pk_test_123', 'secret_key' => 'sk_test_123',
            'mode' => 'test', 'active' => true,
        ])->assertStatus(404);
        $this->deleteJson('/admin/api/payment-methods/99999')->assertStatus(404);
    }

    public function test_validation_fails_for_invalid_data(): void
    {
        $response = $this->postJson('/admin/api/payment-methods', [
            'name' => '',
            'provider' => 'invalid_provider',
            'api_key' => 'short',
            'secret_key' => '',
            'mode' => 'invalid',
        ]);

        $response->assertStatus(422);
    }

    public function test_unauthorized_user_cannot_manage_methods(): void
    {
        // Create admin without permissions
        $admin = AdminUser::factory()->create();
        $this->actingAs($admin, 'admin');

        $this->getJson('/admin/api/payment-methods')->assertStatus(403);
        $this->postJson('/admin/api/payment-methods', [
            'name' => 'Test', 'provider' => 'stripe',
            'api_key' => 'pk_test_123', 'secret_key' => 'sk_test_123',
            'mode' => 'test', 'active' => true,
        ])->assertStatus(403);
    }
}
