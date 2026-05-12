<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class PaymentMethodFrontendTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();
    }

    /** @test */
    public function frontend_can_create_payment_method()
    {
        $data = [
            'name' => 'Frontend Test Payment',
            'provider' => 'stripe',
            'api_key' => 'pk_test_frontend_test_key_1234567890',
            'secret_key' => 'sk_live_frontend_test_key_1234567890',
            'mode' => 'test',
            'active' => true,
        ];

        $response = $this->postJson('/admin/api/payment-methods', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'method' => [
                    'id',
                    'name',
                    'provider',
                    'mode',
                    'active',
                    'created_at',
                    'updated_at',
                ],
            ]);

        $this->assertDatabaseHas('payment_methods', [
            'name' => 'Frontend Test Payment',
            'provider' => 'stripe',
            'mode' => 'test',
            'active' => true,
        ]);
    }

    /** @test */
    public function frontend_can_update_payment_method()
    {
        $paymentMethod = PaymentMethod::factory()->create([
            'name' => 'Original Name',
            'active' => true,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'provider' => 'paypal',
            'api_key' => 'pk_test_updated_key_12345678901234567890',
            'secret_key' => 'sk_live_updated_key_12345678901234567890',
            'mode' => 'live',
            'active' => false,
        ];

        $response = $this->putJson("/admin/api/payment-methods/{$paymentMethod->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'method' => [
                    'id',
                    'name',
                    'provider',
                    'mode',
                    'active',
                    'updated_at',
                ],
            ]);

        $this->assertDatabaseHas('payment_methods', [
            'id' => $paymentMethod->id,
            'name' => 'Updated Name',
            'provider' => 'paypal',
            'mode' => 'live',
            'active' => false,
        ]);
    }

    /** @test */
    public function frontend_can_delete_payment_method()
    {
        $paymentMethod = PaymentMethod::factory()->create();

        $response = $this->deleteJson("/admin/api/payment-methods/{$paymentMethod->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Deleted successfully',
            ]);

        $this->assertDatabaseMissing('payment_methods', [
            'id' => $paymentMethod->id,
        ]);
    }

    /** @test */
    public function frontend_validation_works_for_create()
    {
        $invalidData = [
            'name' => '', // Required
            'provider' => 'invalid_provider',
            'api_key' => 'short', // Too short
            'secret_key' => 'also_short', // Too short
            'mode' => 'invalid_mode',
        ];

        $response = $this->postJson('/admin/api/payment-methods', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'provider',
                'api_key',
                'mode',
            ]);
    }

    /** @test */
    public function frontend_cannot_create_duplicate_payment_method_names()
    {
        // Create first payment method
        PaymentMethod::factory()->create(['name' => 'Duplicate Name']);

        $duplicateData = [
            'name' => 'Duplicate Name', // Duplicate name
            'provider' => 'stripe',
            'api_key' => 'pk_test_long_enough_key_1234567890',
            'secret_key' => 'sk_live_long_enough_key_1234567890',
            'mode' => 'test',
            'active' => true,
        ];

        $response = $this->postJson('/admin/api/payment-methods', $duplicateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function frontend_can_update_payment_method_with_same_name()
    {
        $paymentMethod = PaymentMethod::factory()->create([
            'name' => 'Original Name',
            'active' => true,
        ]);

        $updateData = [
            'name' => 'Original Name', // Same name (should be allowed for same record)
            'provider' => 'paypal',
            'api_key' => 'pk_test_updated_key_12345678901234567890',
            'secret_key' => 'sk_live_updated_key_12345678901234567890',
            'mode' => 'live',
            'active' => false,
        ];

        $response = $this->putJson("/admin/api/payment-methods/{$paymentMethod->id}", $updateData);

        $response->assertStatus(200);
    }

    /** @test */
    public function frontend_cannot_update_payment_method_with_duplicate_name()
    {
        // Create two payment methods
        $method1 = PaymentMethod::factory()->create(['name' => 'Method One']);
        $method2 = PaymentMethod::factory()->create(['name' => 'Method Two']);

        $updateData = [
            'name' => 'Method One', // Duplicate of method1
            'provider' => 'stripe',
            'api_key' => 'pk_test_updated_key_12345678901234567890',
            'secret_key' => 'sk_live_updated_key_12345678901234567890',
            'mode' => 'test',
            'active' => true,
        ];

        $response = $this->putJson("/admin/api/payment-methods/{$method2->id}", $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function frontend_can_toggle_payment_method_active_status()
    {
        $paymentMethod = PaymentMethod::factory()->create([
            'name' => 'Toggle Test Method',
            'active' => true,
        ]);

        // Toggle from active to inactive
        $response = $this->patchJson("/admin/api/payment-methods/{$paymentMethod->id}/toggle-active");

        $response->assertStatus(200);
        $response->assertJsonStructure(['method' => ['id', 'name', 'active']]);

        $this->assertFalse($response->json()['method']['active']);

        // Toggle back from inactive to active
        $response2 = $this->patchJson("/admin/api/payment-methods/{$paymentMethod->id}/toggle-active");

        $response2->assertStatus(200);
        $this->assertTrue($response2->json()['method']['active']);
    }

    /** @test */
    public function frontend_cannot_toggle_nonexistent_payment_method()
    {
        $response = $this->patchJson('/admin/api/payment-methods/99999/toggle-active');

        $response->assertStatus(404);
    }
}
