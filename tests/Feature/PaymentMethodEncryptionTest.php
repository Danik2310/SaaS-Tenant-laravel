<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class PaymentMethodEncryptionTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();
    }

    public function test_api_keys_are_stored_encrypted(): void
    {
        $originalApiKey = 'pk_test_persistence_123456789012345678901234567890';
        $originalSecretKey = 'sk_live_persistence_123456789012345678901234567890';

        $response = $this->postJson('/admin/api/payment-methods', [
            'name' => 'Encryption Test',
            'provider' => 'stripe',
            'api_key' => $originalApiKey,
            'secret_key' => $originalSecretKey,
            'mode' => 'test',
            'active' => true,
        ]);
        $response->assertStatus(201);

        $methodId = $response->json()['method']['id'];
        $method = PaymentMethod::find($methodId);

        $storedApiKey = $method->getAttributes()['api_key'];
        $storedSecretKey = $method->getAttributes()['secret_key'];

        $this->assertNotEquals($originalApiKey, $storedApiKey);
        $this->assertNotEquals($originalSecretKey, $storedSecretKey);
    }

    public function test_api_keys_are_decrypted_on_access(): void
    {
        $originalApiKey = 'pk_test_decrypt_123456789012345678901234567890';

        $response = $this->postJson('/admin/api/payment-methods', [
            'name' => 'Decryption Test',
            'provider' => 'stripe',
            'api_key' => $originalApiKey,
            'secret_key' => 'sk_live_decrypt_123456789012345678901234567890',
            'mode' => 'test',
            'active' => true,
        ]);
        $response->assertStatus(201);

        $methodId = $response->json()['method']['id'];
        $method = PaymentMethod::find($methodId);

        $this->assertEquals($originalApiKey, $method->api_key);
    }

    public function test_encryption_is_consistent_across_multiple_methods(): void
    {
        $originalApiKey = 'pk_test_consistency_123456789012345678901234567890';

        $method1 = PaymentMethod::create([
            'name' => 'Consistency Test 1',
            'provider' => 'stripe',
            'api_key' => $originalApiKey,
            'secret_key' => 'sk_live_consistency_123456789012345678901234567890',
            'mode' => 'test',
            'active' => true,
        ]);

        $method2 = PaymentMethod::create([
            'name' => 'Consistency Test 2',
            'provider' => 'stripe',
            'api_key' => $originalApiKey,
            'secret_key' => 'sk_live_consistency_123456789012345678901234567890',
            'mode' => 'test',
            'active' => true,
        ]);

        // Encrypted values differ due to random IV, but decrypted values match
        $this->assertNotEquals(
            $method1->getAttributes()['api_key'],
            $method2->getAttributes()['api_key']
        );
        $this->assertEquals($originalApiKey, $method1->api_key);
        $this->assertEquals($originalApiKey, $method2->api_key);
    }
}
