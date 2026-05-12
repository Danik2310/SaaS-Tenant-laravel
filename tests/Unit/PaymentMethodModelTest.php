<?php

namespace Tests\Unit;

use App\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 🧪 Test: API key is encrypted when set
     */
    public function test_api_key_is_encrypted_when_set()
    {
        $plainApiKey = 'sk_test_123456789012345678901234567890';
        $method = new PaymentMethod;
        $method->api_key = $plainApiKey;

        // The stored value should be encrypted
        $this->assertNotEquals($plainApiKey, $method->getAttributes()['api_key']);
    }

    /**
     * 🧪 Test: API key is decrypted when accessed
     */
    public function test_api_key_is_decrypted_when_accessed()
    {
        $plainApiKey = 'sk_test_123456789012345678901234567890';
        $method = new PaymentMethod;
        $method->api_key = $plainApiKey;

        // Accessing the attribute should return the plain text
        $this->assertEquals($plainApiKey, $method->api_key);
    }

    /**
     * 🧪 Test: Secret key is encrypted when set
     */
    public function test_secret_key_is_encrypted_when_set()
    {
        $plainSecretKey = 'sk_live_123456789012345678901234567890';
        $method = new PaymentMethod;
        $method->secret_key = $plainSecretKey;

        // The stored value should be encrypted
        $this->assertNotEquals($plainSecretKey, $method->getAttributes()['secret_key']);
    }

    /**
     * 🧪 Test: Secret key is decrypted when accessed
     */
    public function test_secret_key_is_decrypted_when_accessed()
    {
        $plainSecretKey = 'sk_live_123456789012345678901234567890';
        $method = new PaymentMethod;
        $method->secret_key = $plainSecretKey;

        // Accessing the attribute should return the plain text
        $this->assertEquals($plainSecretKey, $method->secret_key);
    }

    /**
     * 🧪 Test: Fillable attributes are correct
     */
    public function test_fillable_attributes()
    {
        $method = new PaymentMethod;
        $expectedFillable = ['name', 'provider', 'api_key', 'secret_key', 'mode', 'active'];

        $this->assertEquals($expectedFillable, $method->getFillable());
    }

    /**
     * 🧪 Test: Casts are correct
     */
    public function test_casts()
    {
        $method = new PaymentMethod;
        $casts = $method->getCasts();

        // Check specific casts we defined
        $this->assertEquals('boolean', $casts['active']);
        $this->assertEquals('datetime', $casts['created_at']);
        $this->assertEquals('datetime', $casts['updated_at']);
    }

    /**
     * 🧪 Test: Mass assignment protection
     */
    public function test_mass_assignment_protection()
    {
        $method = new PaymentMethod;

        // Test that guarded attributes are protected
        $method->fill([
            'fillable_attr' => 'value',
            'id' => 999, // Should be protected
            'created_at' => now(), // Should be protected
            'updated_at' => now(), // Should be protected
        ]);

        $this->assertArrayNotHasKey('id', $method->getAttributes());
        $this->assertArrayNotHasKey('created_at', $method->getAttributes());
        $this->assertArrayNotHasKey('updated_at', $method->getAttributes());
    }

    /**
     * 🧪 Test: Encryption with special characters
     */
    public function test_encryption_with_special_characters()
    {
        $specialApiKey = 'sk_test_!@#$%^&*()_+{}|:<>?[]\;\'",./1234567890';
        $specialSecretKey = 'sk_live_特殊字符_ñáéíóú_1234567890';

        $method = new PaymentMethod;
        $method->api_key = $specialApiKey;
        $method->secret_key = $specialSecretKey;

        // Verify encryption worked
        $this->assertNotEquals($specialApiKey, $method->getAttributes()['api_key']);
        $this->assertNotEquals($specialSecretKey, $method->getAttributes()['secret_key']);

        // Verify decryption works
        $this->assertEquals($specialApiKey, $method->api_key);
        $this->assertEquals($specialSecretKey, $method->secret_key);
    }

    /**
     * 🧪 Test: Encryption with empty strings
     */
    public function test_encryption_with_empty_strings()
    {
        $method = new PaymentMethod;
        $method->api_key = '';
        $method->secret_key = '';

        // Empty strings are converted to null by the mutator
        $this->assertNull($method->getAttributes()['api_key']);
        $this->assertNull($method->getAttributes()['secret_key']);

        // And should decrypt to null
        $this->assertNull($method->api_key);
        $this->assertNull($method->secret_key);
    }

    /**
     * 🧪 Test: Model uses HasFactory trait
     */
    public function test_model_uses_has_factory_trait()
    {
        $method = new PaymentMethod;
        $traits = class_uses($method);

        $this->assertArrayHasKey('Illuminate\Database\Eloquent\Factories\HasFactory', $traits);
    }

    /**
     * 🧪 Test: Model has correct table name
     */
    public function test_model_has_correct_table_name()
    {
        $method = new PaymentMethod;
        $this->assertEquals('payment_methods', $method->getTable());
    }

    /**
     * 🧪 Test: Model has correct primary key
     */
    public function test_model_has_correct_primary_key()
    {
        $method = new PaymentMethod;
        $this->assertEquals('id', $method->getKeyName());
    }

    /**
     * 🧪 Test: Model has timestamps enabled
     */
    public function test_model_has_timestamps_enabled()
    {
        $method = new PaymentMethod;
        $this->assertTrue($method->usesTimestamps());
    }

    /**
     * 🧪 Test: Factory creates valid instances
     */
    public function test_factory_creates_valid_instances()
    {
        $method = PaymentMethod::factory()->create();

        $this->assertInstanceOf(PaymentMethod::class, $method);
        $this->assertNotNull($method->id);
        $this->assertNotNull($method->name);
        $this->assertNotNull($method->provider);
        $this->assertNotNull($method->mode);
        $this->assertIsBool($method->active);
        $this->assertNotNull($method->created_at);
        $this->assertNotNull($method->updated_at);
    }

    /**
     * 🧪 Test: Factory respects fillable constraints
     */
    public function test_factory_respects_fillable_constraints()
    {
        $method = PaymentMethod::factory()->create();

        // Check that all fillable attributes are set
        $fillable = $method->getFillable();
        foreach ($fillable as $attribute) {
            $this->assertArrayHasKey($attribute, $method->getAttributes());
        }
    }

    /**
     * 🧪 Test: Model can be serialized
     */
    public function test_model_can_be_serialized()
    {
        $method = PaymentMethod::factory()->create();

        $serialized = $method->toArray();

        $this->assertIsArray($serialized);
        $this->assertArrayHasKey('id', $serialized);
        $this->assertArrayHasKey('name', $serialized);
        $this->assertArrayHasKey('provider', $serialized);
        $this->assertArrayHasKey('mode', $serialized);
        $this->assertArrayHasKey('active', $serialized);
        $this->assertArrayHasKey('created_at', $serialized);
        $this->assertArrayHasKey('updated_at', $serialized);

        // API keys should not be in serialized output for security
        $this->assertArrayNotHasKey('api_key', $serialized);
        $this->assertArrayNotHasKey('secret_key', $serialized);
    }

    /**
     * 🧪 Test: Model can be converted to JSON
     */
    public function test_model_can_be_converted_to_json()
    {
        $method = PaymentMethod::factory()->create();

        $json = $method->toJson();

        $this->assertIsString($json);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('id', $decoded);
        $this->assertArrayHasKey('name', $decoded);
    }

    /**
     * 🧪 Test: Model attributes are properly cast when retrieved from database
     */
    public function test_model_attributes_are_properly_cast_when_retrieved_from_database()
    {
        $method = PaymentMethod::factory()->create(['active' => true]);

        // Retrieve from database
        $retrievedMethod = PaymentMethod::find($method->id);

        $this->assertIsBool($retrievedMethod->active);
        $this->assertTrue($retrievedMethod->active);
        $this->assertInstanceOf(Carbon::class, $retrievedMethod->created_at);
        $this->assertInstanceOf(Carbon::class, $retrievedMethod->updated_at);
    }
}
