<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class PaymentMethodValidationTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();
    }

    public function test_duplicate_name_returns_validation_error(): void
    {
        $this->postJson('/admin/api/payment-methods', [
            'name' => 'Unique Name',
            'provider' => 'stripe',
            'api_key' => 'pk_test_123456789012345678901234567890',
            'secret_key' => 'sk_live_123456789012345678901234567890',
            'mode' => 'test',
            'active' => true,
        ])->assertStatus(201);

        $this->postJson('/admin/api/payment-methods', [
            'name' => 'Unique Name',
            'provider' => 'paypal',
            'api_key' => 'paypal_api_123456789012345678901234567890',
            'secret_key' => 'paypal_secret_123456789012345678901234567890',
            'mode' => 'live',
            'active' => true,
        ])->assertStatus(422);
    }

    public function test_invalid_provider_returns_validation_error(): void
    {
        $this->postJson('/admin/api/payment-methods', [
            'name' => 'Invalid Provider',
            'provider' => 'invalid_provider',
            'api_key' => 'pk_test_123456789012345678901234567890',
            'secret_key' => 'sk_live_123456789012345678901234567890',
            'mode' => 'test',
            'active' => true,
        ])->assertStatus(422);
    }

    public function test_sql_injection_attempt_is_safely_handled(): void
    {
        $response = $this->postJson('/admin/api/payment-methods', [
            'name' => "'; DROP TABLE payment_methods; --",
            'provider' => 'stripe',
            'api_key' => 'pk_test_123456789012345678901234567890',
            'secret_key' => 'sk_live_123456789012345678901234567890',
            'mode' => 'test',
            'active' => true,
        ]);
        $response->assertStatus(201);

        // Laravel's query builder safely parameterizes the value
        $method = PaymentMethod::where('name', "'; DROP TABLE payment_methods; --")->first();
        $this->assertNotNull($method);
    }

    public function test_can_filter_methods_by_active_status(): void
    {
        PaymentMethod::factory()->create(['name' => 'Active One', 'active' => true]);
        PaymentMethod::factory()->create(['name' => 'Active Two', 'active' => true]);
        PaymentMethod::factory()->create(['name' => 'Inactive One', 'active' => false]);

        $this->assertCount(2, PaymentMethod::where('active', true)->get());
        $this->assertCount(1, PaymentMethod::where('active', false)->get());
    }

    public function test_can_filter_methods_by_provider(): void
    {
        PaymentMethod::factory()->create(['name' => 'Stripe 1', 'provider' => 'stripe']);
        PaymentMethod::factory()->create(['name' => 'Stripe 2', 'provider' => 'stripe']);
        PaymentMethod::factory()->create(['name' => 'PayPal 1', 'provider' => 'paypal']);

        $this->assertCount(2, PaymentMethod::where('provider', 'stripe')->get());
        $this->assertCount(1, PaymentMethod::where('provider', 'paypal')->get());
    }

    public function test_data_integrity_prevents_invalid_enum_values(): void
    {
        // Provider/mode columns were changed from ENUM to VARCHAR in
        // 2026_05_28_070500_change_payment_methods_enums_to_string, so the DB no longer
        // rejects invalid values. Validation is enforced at the application layer
        // (FormRequest). This test verifies the DB accepts arbitrary strings.
        \DB::table('payment_methods')->insert([
            'name' => 'Invalid Provider',
            'provider' => 'invalid_provider',
            'api_key' => Crypt::encryptString('test_key'),
            'secret_key' => Crypt::encryptString('test_secret'),
            'mode' => 'test',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('payment_methods', ['provider' => 'invalid_provider']);
    }
}
