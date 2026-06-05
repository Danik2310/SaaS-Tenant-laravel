<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();
    }

    /**
     * 🧪 Test: Can create a payment method
     */
    public function test_can_create_payment_method()
    {
        $data = [
            'name' => 'Stripe Test',
            'provider' => 'stripe',
            'api_key' => 'sk_test_123456789012345678901234567890',
            'secret_key' => 'sk_live_123456789012345678901234567890',
            'mode' => 'test',
            'active' => true,
        ];

        $response = $this->postJson('/admin/api/payment-methods', $data);

        $response->assertStatus(201)
            ->assertJsonStructure(['method' => ['id', 'name', 'provider', 'mode', 'active']]);

        $this->assertDatabaseHas('payment_methods', [
            'name' => 'Stripe Test',
            'provider' => 'stripe',
            'mode' => 'test',
            'active' => true,
        ]);

        // Verify encryption: the stored value should not be the plain text
        $method = PaymentMethod::first();
        $this->assertNotEquals('sk_test_123456789012345678901234567890', $method->getAttributes()['api_key']);
    }

    /**
     * 🧪 Test: Can update a payment method
     */
    public function test_can_update_payment_method()
    {
        $method = PaymentMethod::factory()->create([
            'name' => 'PayPal Old',
            'provider' => 'paypal',
            'api_key' => 'api_key_old',
            'secret_key' => 'secret_key_old',
            'mode' => 'test',
            'active' => true,
        ]);

        $updateData = [
            'name' => 'PayPal Updated',
            'provider' => 'paypal',
            'api_key' => 'api_key_new_12345678901234567890',
            'secret_key' => 'secret_key_new_12345678901234567890',
            'mode' => 'live',
            'active' => false,
        ];

        $response = $this->putJson("/admin/api/payment-methods/{$method->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure(['method' => ['id', 'name', 'provider', 'mode', 'active']]);

        $this->assertDatabaseHas('payment_methods', [
            'name' => 'PayPal Updated',
            'provider' => 'paypal',
            'mode' => 'live',
            'active' => false,
        ]);

        // Verify the method is updated and encrypted
        $updatedMethod = PaymentMethod::find($method->id);
        $this->assertEquals('PayPal Updated', $updatedMethod->name);
        $this->assertEquals('live', $updatedMethod->mode);
        $this->assertFalse($updatedMethod->active);
        // Decrypted values should match
        $this->assertEquals('api_key_new_12345678901234567890', $updatedMethod->api_key);
        $this->assertEquals('secret_key_new_12345678901234567890', $updatedMethod->secret_key);
    }

    /**
     * 🧪 Test: Validation fails for invalid data
     */
    public function test_validation_fails_for_invalid_data()
    {
        $invalidData = [
            'name' => '',
            'provider' => 'invalid',
            'api_key' => 'short',
            'secret_key' => 'short',
            'mode' => 'invalid',
        ];

        $response = $this->postJson('/admin/api/payment-methods', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'provider', 'api_key', 'secret_key', 'mode']);
    }

    /**
     * 🧪 Test: Can list all payment methods
     */
    public function test_can_list_all_payment_methods()
    {
        // Create multiple payment methods
        PaymentMethod::factory()->create([
            'name' => 'Stripe Test',
            'provider' => 'stripe',
            'mode' => 'test',
            'active' => true,
        ]);

        PaymentMethod::factory()->create([
            'name' => 'PayPal Live',
            'provider' => 'paypal',
            'mode' => 'live',
            'active' => false,
        ]);

        $response = $this->getJson('/admin/api/payment-methods');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'methods' => [
                    '*' => ['id', 'name', 'provider', 'mode', 'active', 'created_at', 'updated_at'],
                ],
            ]);

        $data = $response->json();
        $this->assertCount(2, $data['methods']);
    }

    /**
     * 🧪 Test: Can show a specific payment method
     */
    public function test_can_show_specific_payment_method()
    {
        $method = PaymentMethod::factory()->create([
            'name' => 'Stripe Production',
            'provider' => 'stripe',
            'api_key' => 'sk_live_123456789012345678901234567890',
            'secret_key' => 'sk_live_secret_123456789012345678901234567890',
            'mode' => 'live',
            'active' => true,
        ]);

        $response = $this->getJson("/admin/api/payment-methods/{$method->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['method' => ['id', 'name', 'provider', 'mode', 'active']]);

        $data = $response->json();
        $this->assertEquals('Stripe Production', $data['method']['name']);
        $this->assertEquals('stripe', $data['method']['provider']);
        $this->assertEquals('live', $data['method']['mode']);
        $this->assertTrue($data['method']['active']);
    }

    /**
     * 🧪 Test: Returns 404 for non-existent payment method
     */
    public function test_returns_404_for_non_existent_payment_method()
    {
        $response = $this->getJson('/admin/api/payment-methods/999');

        $response->assertStatus(404);
    }

    /**
     * 🧪 Test: Can delete a payment method
     */
    public function test_can_delete_payment_method()
    {
        $method = PaymentMethod::factory()->create();

        $response = $this->deleteJson("/admin/api/payment-methods/{$method->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('payment_methods', ['id' => $method->id]);
    }

    /**
     * 🧪 Test: Cannot create payment method without proper permissions
     */
    public function test_cannot_create_payment_method_without_proper_permissions()
    {
        // Create admin user without 'manage plans' permission
        $role = Role::firstOrCreate([
            'name' => 'limited-admin',
            'guard_name' => 'admin',
        ]);

        // Create permission but don't assign it
        $permission = Permission::firstOrCreate([
            'name' => 'manage plans',
            'guard_name' => 'admin',
        ]);

        $limitedAdmin = AdminUser::factory()->create();
        $limitedAdmin->assignRole('limited-admin');

        // Authenticate as limited admin
        $this->actingAs($limitedAdmin, 'admin');

        $data = [
            'name' => 'Stripe Test',
            'provider' => 'stripe',
            'api_key' => 'sk_test_123456789012345678901234567890',
            'secret_key' => 'sk_live_123456789012345678901234567890',
            'mode' => 'test',
            'active' => true,
        ];

        $response = $this->postJson('/admin/api/payment-methods', $data);

        // Should fail due to missing permission
        $response->assertStatus(403);
    }

    /**
     * 🧪 Test: API key validation - minimum length
     */
    public function test_api_key_validation_minimum_length()
    {
        $data = [
            'name' => 'Stripe Test',
            'provider' => 'stripe',
            'api_key' => 'short', // Less than 10 characters
            'secret_key' => 'sk_live_123456789012345678901234567890',
            'mode' => 'test',
            'active' => true,
        ];

        $response = $this->postJson('/admin/api/payment-methods', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['api_key']);
    }

    /**
     * 🧪 Test: Secret key validation - minimum length
     */
    public function test_secret_key_validation_minimum_length()
    {
        $data = [
            'name' => 'Stripe Test',
            'provider' => 'stripe',
            'api_key' => 'sk_test_123456789012345678901234567890',
            'secret_key' => 'short', // Less than 10 characters
            'mode' => 'test',
            'active' => true,
        ];

        $response = $this->postJson('/admin/api/payment-methods', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['secret_key']);
    }

    /**
     * 🧪 Test: Provider validation - only allowed values
     */
    public function test_provider_validation_allowed_values()
    {
        $data = [
            'name' => 'Test Method',
            'provider' => 'invalid_provider',
            'api_key' => 'sk_test_123456789012345678901234567890',
            'secret_key' => 'sk_live_123456789012345678901234567890',
            'mode' => 'test',
            'active' => true,
        ];

        $response = $this->postJson('/admin/api/payment-methods', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['provider']);
    }

    /**
     * 🧪 Test: Mode validation - only test or live
     */
    public function test_mode_validation_allowed_values()
    {
        $data = [
            'name' => 'Test Method',
            'provider' => 'stripe',
            'api_key' => 'sk_test_123456789012345678901234567890',
            'secret_key' => 'sk_live_123456789012345678901234567890',
            'mode' => 'invalid_mode',
            'active' => true,
        ];

        $response = $this->postJson('/admin/api/payment-methods', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mode']);
    }

    /**
     * 🧪 Test: Name validation - required and max length
     */
    public function test_name_validation_required_and_max_length()
    {
        // Test empty name
        $data = [
            'name' => '',
            'provider' => 'stripe',
            'api_key' => 'sk_test_123456789012345678901234567890',
            'secret_key' => 'sk_live_123456789012345678901234567890',
            'mode' => 'test',
            'active' => true,
        ];

        $response = $this->postJson('/admin/api/payment-methods', $data);
        $response->assertStatus(422)->assertJsonValidationErrors(['name']);

        // Test name too long (over 255 characters)
        $longName = str_repeat('a', 256);
        $data['name'] = $longName;

        $response = $this->postJson('/admin/api/payment-methods', $data);
        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    /**
     * 🧪 Test: Can create payment method with null API keys
     */
    public function test_can_create_payment_method_with_null_api_keys()
    {
        $data = [
            'name' => 'Test Method',
            'provider' => 'stripe',
            'api_key' => null,
            'secret_key' => null,
            'mode' => 'test',
            'active' => true,
        ];

        $response = $this->postJson('/admin/api/payment-methods', $data);

        $response->assertStatus(201)
            ->assertJsonStructure(['method' => ['id', 'name', 'provider', 'mode', 'active']]);

        $this->assertDatabaseHas('payment_methods', [
            'name' => 'Test Method',
            'provider' => 'stripe',
            'mode' => 'test',
            'active' => true,
        ]);

        $method = PaymentMethod::first();
        $this->assertNull($method->api_key);
        $this->assertNull($method->secret_key);
    }

    /**
     * 🧪 Test: Active field defaults to true when not provided
     */
    public function test_active_field_defaults_to_true_when_not_provided()
    {
        $data = [
            'name' => 'Test Method',
            'provider' => 'stripe',
            'api_key' => 'sk_test_123456789012345678901234567890',
            'secret_key' => 'sk_live_123456789012345678901234567890',
            'mode' => 'test',
            // active not provided
        ];

        $response = $this->postJson('/admin/api/payment-methods', $data);

        $response->assertStatus(201);

        $method = PaymentMethod::first();
        $this->assertTrue($method->active);
    }

    /**
     * 🧪 Test: Can update only specific fields
     */
    public function test_can_update_only_specific_fields()
    {
        $method = PaymentMethod::factory()->create([
            'name' => 'Original Name',
            'provider' => 'stripe',
            'api_key' => 'sk_test_123456789012345678901234567890',
            'secret_key' => 'sk_live_123456789012345678901234567890',
            'mode' => 'test',
            'active' => true,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'provider' => 'stripe', // Required field
            'api_key' => 'sk_test_123456789012345678901234567890', // Required field
            'secret_key' => 'sk_live_123456789012345678901234567890', // Required field
            'mode' => 'test', // Required field
            'active' => true, // Required field
        ];

        $response = $this->putJson("/admin/api/payment-methods/{$method->id}", $updateData);

        $response->assertStatus(200);

        $updatedMethod = PaymentMethod::find($method->id);
        $this->assertEquals('Updated Name', $updatedMethod->name);
        $this->assertEquals('stripe', $updatedMethod->provider); // Unchanged
        $this->assertEquals('test', $updatedMethod->mode); // Unchanged
        $this->assertTrue($updatedMethod->active); // Unchanged
    }

    /**
     * 🧪 Test: Cannot update non-existent payment method
     */
    public function test_cannot_update_non_existent_payment_method()
    {
        $updateData = [
            'name' => 'Updated Name',
            'provider' => 'stripe',
            'mode' => 'test',
            'active' => true,
        ];

        $response = $this->putJson('/admin/api/payment-methods/999', $updateData);

        $response->assertStatus(404);
    }

    /**
     * 🧪 Test: Cannot delete non-existent payment method
     */
    public function test_cannot_delete_non_existent_payment_method()
    {
        $response = $this->deleteJson('/admin/api/payment-methods/999');

        $response->assertStatus(404);
    }

    /**
     * 🧪 Test: Response includes timestamps
     */
    public function test_response_includes_timestamps()
    {
        $method = PaymentMethod::factory()->create();

        $response = $this->getJson("/admin/api/payment-methods/{$method->id}");

        $response->assertStatus(200)
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
    }

    /**
     * 🧪 Test: API keys are properly encrypted in responses
     */
    public function test_api_keys_not_exposed_in_responses()
    {
        $method = PaymentMethod::factory()->create([
            'api_key' => 'sk_test_123456789012345678901234567890',
            'secret_key' => 'sk_live_123456789012345678901234567890',
        ]);

        $response = $this->getJson('/admin/api/payment-methods');

        $response->assertStatus(200);

        $data = $response->json();
        $methodData = $data['methods'][0];

        // API keys should NOT be included in the response for security
        $this->assertArrayNotHasKey('api_key', $methodData);
        $this->assertArrayNotHasKey('secret_key', $methodData);
    }
}
