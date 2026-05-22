<?php

namespace Tests\Integration;

use App\Models\AdminUser;
use App\Models\PaymentMethod;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentMethodIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the super-admin role if it doesn't exist
        $role = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'admin',
        ]);

        // Create permissions
        $permissions = [
            'manage tenants',
            'manage staff',
            'manage plans',
            'impersonate tenants',
            'manage profile',
        ];

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'admin',
            ]);
            $role->givePermissionTo($permission);
        }

        // Create admin user with permissions
        $admin = AdminUser::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin, 'admin');
    }

    /**
     * 🧪 Integration Test: Complete CRUD flow
     */
    public function test_complete_crud_flow()
    {
        // 1. CREATE
        $createData = [
            'name' => 'Stripe Integration Test',
            'provider' => 'stripe',
            'api_key' => 'pk_test_123456789012345678901234567890',
            'secret_key' => 'sk_live_123456789012345678901234567890',
            'mode' => 'test',
            'active' => true,
        ];

        $createResponse = $this->postJson('/admin/api/payment-methods', $createData);
        $createResponse->assertStatus(201);

        $methodId = $createResponse->json()['method']['id'];

        // 2. READ (List all)
        $listResponse = $this->getJson('/admin/api/payment-methods');
        $listResponse->assertStatus(200);
        $this->assertCount(1, $listResponse->json()['methods']);

        // 3. READ (Single item)
        $showResponse = $this->getJson("/admin/api/payment-methods/{$methodId}");
        $showResponse->assertStatus(200);
        $this->assertEquals('Stripe Integration Test', $showResponse->json()['method']['name']);

        // 4. UPDATE
        $updateData = [
            'name' => 'Stripe Integration Test Updated',
            'provider' => 'stripe',
            'api_key' => 'pk_test_updated_123456789012345678901234567890',
            'secret_key' => 'sk_live_updated_123456789012345678901234567890',
            'mode' => 'live',
            'active' => false,
        ];

        $updateResponse = $this->putJson("/admin/api/payment-methods/{$methodId}", $updateData);
        $updateResponse->assertStatus(200);

        // Verify update in database
        $updatedMethod = PaymentMethod::find($methodId);
        $this->assertEquals('Stripe Integration Test Updated', $updatedMethod->name);
        $this->assertEquals('live', $updatedMethod->mode);
        $this->assertFalse($updatedMethod->active);
        $this->assertEquals('pk_test_updated_123456789012345678901234567890', $updatedMethod->api_key);

        // 5. DELETE
        $deleteResponse = $this->deleteJson("/admin/api/payment-methods/{$methodId}");
        $deleteResponse->assertStatus(204);

        // Verify deletion
        $this->assertDatabaseMissing('payment_methods', ['id' => $methodId]);
    }

    /**
     * 🧪 Integration Test: Encryption/decryption persistence
     */
    public function test_encryption_decryption_persistence()
    {
        $originalApiKey = 'pk_test_persistence_123456789012345678901234567890';
        $originalSecretKey = 'sk_live_persistence_123456789012345678901234567890';

        // Create method
        $createData = [
            'name' => 'Encryption Test',
            'provider' => 'stripe',
            'api_key' => $originalApiKey,
            'secret_key' => $originalSecretKey,
            'mode' => 'test',
            'active' => true,
        ];

        $response = $this->postJson('/admin/api/payment-methods', $createData);
        $response->assertStatus(201);

        $methodId = $response->json()['method']['id'];

        // Verify stored values are encrypted
        $method = PaymentMethod::find($methodId);
        $storedApiKey = $method->getAttributes()['api_key'];
        $storedSecretKey = $method->getAttributes()['secret_key'];

        $this->assertNotEquals($originalApiKey, $storedApiKey);
        $this->assertNotEquals($originalSecretKey, $storedSecretKey);

        // Verify we can decrypt them back
        $this->assertEquals($originalApiKey, $method->api_key);
        $this->assertEquals($originalSecretKey, $method->secret_key);

        // Verify encryption/decryption is consistent (can decrypt back to original)
        $method2 = PaymentMethod::create([
            'name' => 'Encryption Test 2',
            'provider' => 'stripe',
            'api_key' => $originalApiKey,
            'secret_key' => $originalSecretKey,
            'mode' => 'test',
            'active' => true,
        ]);

        // The encrypted strings will be different (due to random IV), but decryption should work
        $this->assertEquals($originalApiKey, $method2->api_key);
        $this->assertEquals($originalSecretKey, $method2->secret_key);
    }

    /**
     * 🧪 Integration Test: Multiple payment methods management
     */
    public function test_multiple_payment_methods_management()
    {
        // Create multiple payment methods
        $methods = [
            [
                'name' => 'Stripe Test',
                'provider' => 'stripe',
                'api_key' => 'pk_test_stripe_123456789012345678901234567890',
                'secret_key' => 'sk_live_stripe_123456789012345678901234567890',
                'mode' => 'test',
                'active' => true,
            ],
            [
                'name' => 'PayPal Live',
                'provider' => 'paypal',
                'api_key' => 'paypal_api_123456789012345678901234567890',
                'secret_key' => 'paypal_secret_123456789012345678901234567890',
                'mode' => 'live',
                'active' => true,
            ],
            [
                'name' => 'Stripe Live',
                'provider' => 'stripe',
                'api_key' => 'sk_live_stripe_prod_123456789012345678901234567890',
                'secret_key' => 'sk_live_stripe_prod_secret_123456789012345678901234567890',
                'mode' => 'live',
                'active' => false,
            ],
        ];

        $createdIds = [];
        foreach ($methods as $methodData) {
            $response = $this->postJson('/admin/api/payment-methods', $methodData);
            $response->assertStatus(201);
            $createdIds[] = $response->json()['method']['id'];
        }

        // Verify all methods were created
        $listResponse = $this->getJson('/admin/api/payment-methods');
        $listResponse->assertStatus(200);
        $this->assertCount(3, $listResponse->json()['methods']);

        // Test filtering by active status (simulate frontend filtering)
        $activeMethods = PaymentMethod::where('active', true)->get();
        $this->assertCount(2, $activeMethods);

        $inactiveMethods = PaymentMethod::where('active', false)->get();
        $this->assertCount(1, $inactiveMethods);

        // Test filtering by provider
        $stripeMethods = PaymentMethod::where('provider', 'stripe')->get();
        $this->assertCount(2, $stripeMethods);

        $paypalMethods = PaymentMethod::where('provider', 'paypal')->get();
        $this->assertCount(1, $paypalMethods);

        // Test filtering by mode
        $liveMethods = PaymentMethod::where('mode', 'live')->get();
        $this->assertCount(2, $liveMethods);

        $testMethods = PaymentMethod::where('mode', 'test')->get();
        $this->assertCount(1, $testMethods);
    }

    /**
     * 🧪 Integration Test: Error handling and validation
     */
    public function test_error_handling_and_validation()
    {
        // Test duplicate names (if we want unique names)
        $data1 = [
            'name' => 'Duplicate Name',
            'provider' => 'stripe',
            'api_key' => 'pk_test_123456789012345678901234567890',
            'secret_key' => 'sk_live_123456789012345678901234567890',
            'mode' => 'test',
            'active' => true,
        ];

        $data2 = [
            'name' => 'Duplicate Name', // Same name
            'provider' => 'paypal',
            'api_key' => 'paypal_api_123456789012345678901234567890',
            'secret_key' => 'paypal_secret_123456789012345678901234567890',
            'mode' => 'live',
            'active' => true,
        ];

        $this->postJson('/admin/api/payment-methods', $data1)->assertStatus(201);
        // Now we have unique constraint on name, so this should fail
        $this->postJson('/admin/api/payment-methods', $data2)->assertStatus(422);

        // Test invalid provider enum
        $invalidData = [
            'name' => 'Invalid Provider',
            'provider' => 'invalid_provider',
            'api_key' => 'pk_test_123456789012345678901234567890',
            'secret_key' => 'sk_live_123456789012345678901234567890',
            'mode' => 'test',
            'active' => true,
        ];

        $this->postJson('/admin/api/payment-methods', $invalidData)->assertStatus(422);

        // Test SQL injection attempt (should be safe due to validation)
        $maliciousData = [
            'name' => "'; DROP TABLE payment_methods; --",
            'provider' => 'stripe',
            'api_key' => 'pk_test_123456789012345678901234567890',
            'secret_key' => 'sk_live_123456789012345678901234567890',
            'mode' => 'test',
            'active' => true,
        ];

        $response = $this->postJson('/admin/api/payment-methods', $maliciousData);
        $response->assertStatus(201); // Should succeed but sanitized

        // Verify the malicious name was stored as-is (Laravel protects against SQL injection)
        $method = PaymentMethod::where('name', "'; DROP TABLE payment_methods; --")->first();
        $this->assertNotNull($method);
        $this->assertEquals("'; DROP TABLE payment_methods; --", $method->name);
    }

    /**
     * 🧪 Integration Test: Performance with multiple records
     */
    public function test_performance_with_multiple_records()
    {
        // Create 50 payment methods
        for ($i = 1; $i <= 50; $i++) {
            PaymentMethod::factory()->create([
                'name' => "Payment Method {$i}",
            ]);
        }

        // Test listing performance
        $startTime = microtime(true);
        $response = $this->getJson('/admin/api/payment-methods');
        $endTime = microtime(true);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(50, $data['methods']);

        // Should complete in reasonable time (< 1 second)
        $executionTime = $endTime - $startTime;
        $this->assertLessThan(1.0, $executionTime, "API response took too long: {$executionTime}s");

        // Test database query performance
        $startTime = microtime(true);
        $methods = PaymentMethod::all();
        $endTime = microtime(true);

        $this->assertCount(50, $methods);
        $executionTime = $endTime - $startTime;
        $this->assertLessThan(0.1, $executionTime, "Database query took too long: {$executionTime}s");
    }

    /**
     * 🧪 Integration Test: Data integrity and constraints
     */
    public function test_data_integrity_and_constraints()
    {
        // Test enum constraints at database level
        $this->expectException(QueryException::class);

        // Try to insert invalid provider directly (bypassing validation)
        \DB::table('payment_methods')->insert([
            'name' => 'Invalid Provider',
            'provider' => 'invalid_provider', // Should fail enum constraint
            'api_key' => Crypt::encryptString('test_key'),
            'secret_key' => Crypt::encryptString('test_secret'),
            'mode' => 'test',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 🧪 Integration Test: Load testing with high volume
     */
    public function test_load_testing_high_volume()
    {
        // Create 10 payment methods (respecting rate limit of 10 creates per hour)
        $methodsData = [];
        for ($i = 1; $i <= 10; $i++) {
            $methodsData[] = [
                'name' => "Load Test Method {$i}",
                'provider' => $i % 2 === 0 ? 'stripe' : 'paypal',
                'api_key' => "pk_test_load_{$i}_".str_repeat('1234567890', 3),
                'secret_key' => "sk_live_load_{$i}_".str_repeat('1234567890', 3),
                'mode' => $i % 3 === 0 ? 'live' : 'test',
                'active' => $i % 4 !== 0, // 75% active
            ];
        }

        // Batch create methods
        $createdIds = [];
        $startTime = microtime(true);
        foreach ($methodsData as $methodData) {
            $response = $this->postJson('/admin/api/payment-methods', $methodData);
            $response->assertStatus(201);
            $createdIds[] = $response->json()['method']['id'];
        }
        $creationTime = microtime(true) - $startTime;

        // Performance assertion for creation
        $this->assertLessThan(5.0, $creationTime, "Batch creation took too long: {$creationTime}s");

        // Test listing performance with records
        $startTime = microtime(true);
        $listResponse = $this->getJson('/admin/api/payment-methods');
        $listTime = microtime(true) - $startTime;

        $listResponse->assertStatus(200);
        $data = $listResponse->json();
        $this->assertGreaterThanOrEqual(10, count($data['methods']));

        // Performance assertion for listing
        $this->assertLessThan(1.0, $listTime, "Listing methods took too long: {$listTime}s");

        // Test filtering performance
        $startTime = microtime(true);
        $activeMethods = PaymentMethod::where('active', true)->count();
        $filterTime = microtime(true) - $startTime;

        $this->assertGreaterThan(7, $activeMethods); // Should have ~7-8 active out of 10
        $this->assertLessThan(0.5, $filterTime, "Filtering took too long: {$filterTime}s");

        // Test concurrent operations simulation
        $updatePromises = [];
        $startTime = microtime(true);

        // Update first 10 methods
        for ($i = 0; $i < 10; $i++) {
            $updateData = [
                'name' => "Updated Load Test Method {$createdIds[$i]}",
                'provider' => 'stripe',
                'api_key' => "pk_test_updated_{$i}_".str_repeat('1234567890', 3),
                'secret_key' => "sk_live_updated_{$i}_".str_repeat('1234567890', 3),
                'mode' => 'live',
                'active' => false,
            ];

            $response = $this->putJson("/admin/api/payment-methods/{$createdIds[$i]}", $updateData);
            $response->assertStatus(200);
        }

        $updateTime = microtime(true) - $startTime;
        $this->assertLessThan(5.0, $updateTime, "Bulk updates took too long: {$updateTime}s");

        // Verify updates were applied
        $updatedCount = PaymentMethod::where('active', false)
            ->where('name', 'like', 'Updated Load Test Method%')
            ->count();
        $this->assertEquals(10, $updatedCount);

        // Cleanup - delete all test methods
        $deleteTime = microtime(true);
        foreach ($createdIds as $id) {
            $this->deleteJson("/admin/api/payment-methods/{$id}")->assertStatus(200);
        }
        $deleteTime = microtime(true) - $deleteTime;

        $this->assertLessThan(15.0, $deleteTime, "Bulk deletion took too long: {$deleteTime}s");

        // Verify cleanup
        $remainingCount = PaymentMethod::where('name', 'like', 'Load Test Method%')->count();
        $this->assertEquals(0, $remainingCount);
    }

    /**
     * 🧪 Integration Test: Memory usage monitoring
     */
    public function test_memory_usage_monitoring()
    {
        $initialMemory = memory_get_usage(true);

        // Create 50 methods and monitor memory usage
        for ($i = 1; $i <= 50; $i++) {
            PaymentMethod::factory()->create([
                'name' => "Memory Test {$i}",
            ]);

            // Check memory every 10 iterations
            if ($i % 10 === 0) {
                $currentMemory = memory_get_usage(true);
                $memoryUsage = ($currentMemory - $initialMemory) / 1024 / 1024; // MB

                // Memory usage should not exceed reasonable limits
                $this->assertLessThan(50.0, $memoryUsage,
                    "Memory usage too high at iteration {$i}: {$memoryUsage}MB");
            }
        }

        $finalMemory = memory_get_usage(true);
        $totalMemoryUsage = ($finalMemory - $initialMemory) / 1024 / 1024;

        // Total memory usage should be reasonable
        $this->assertLessThan(100.0, $totalMemoryUsage,
            "Total memory usage too high: {$totalMemoryUsage}MB");

        // Cleanup
        PaymentMethod::where('name', 'like', 'Memory Test%')->delete();
    }

    /**
     * 🧪 Integration Test: Database connection pooling
     */
    public function test_database_connection_pooling()
    {
        // Test multiple rapid database operations
        $startTime = microtime(true);

        // Perform 20 rapid database operations
        for ($i = 1; $i <= 20; $i++) {
            $method = PaymentMethod::factory()->create([
                'name' => "Connection Pool Test {$i}",
            ]);

            // Immediate read
            $found = PaymentMethod::find($method->id);
            $this->assertNotNull($found);

            // Immediate update
            $found->update(['active' => false]);

            // Immediate delete
            $found->delete();
        }

        $totalTime = microtime(true) - $startTime;

        // Should complete in reasonable time even with connection overhead
        $this->assertLessThan(10.0, $totalTime,
            "Database operations took too long: {$totalTime}s");
    }

    /**
     * 🧪 Integration Test: Rate limiting for payment method operations
     */
    public function test_rate_limiting_for_payment_method_operations()
    {
        // Test rate limiting for create operations (10 per hour limit)
        $createData = [
            'name' => 'Rate Limit Test',
            'provider' => 'stripe',
            'api_key' => 'pk_test_ratelimit_123456789012345678901234567890',
            'secret_key' => 'sk_live_ratelimit_123456789012345678901234567890',
            'mode' => 'test',
            'active' => true,
        ];

        // Make 11 create requests (exceeds the 10 per hour limit)
        for ($i = 1; $i <= 11; $i++) {
            $response = $this->postJson('/admin/api/payment-methods', array_merge($createData, [
                'name' => "Rate Limit Test {$i}",
            ]));

            if ($i <= 10) {
                $response->assertStatus(201);
            } else {
                // 11th request should be rate limited
                $response->assertStatus(429);
                $response->assertJsonStructure([
                    'error',
                    'message',
                    'retry_after',
                    'limit',
                    'limit_type',
                ]);
                break; // Stop after confirming rate limiting works
            }
        }

        // Test rate limiting for read operations (200 per hour limit)
        // Make enough requests to potentially trigger rate limiting
        $readRequests = 0;
        $rateLimited = false;

        while ($readRequests < 50 && ! $rateLimited) { // Reasonable number for testing
            $response = $this->getJson('/admin/api/payment-methods');
            $readRequests++;

            if ($response->getStatusCode() === 429) {
                $rateLimited = true;
                $response->assertJsonStructure([
                    'error',
                    'message',
                    'retry_after',
                    'limit',
                    'limit_type',
                ]);
            } else {
                $response->assertStatus(200);
            }
        }

        // Verify rate limit headers are present in successful responses
        $response = $this->getJson('/admin/api/payment-methods');
        if ($response->getStatusCode() === 200) {
            $response->assertHeader('X-RateLimit-Limit');
            $response->assertHeader('X-RateLimit-Remaining');
        }
    }

    /**
     * 🧪 Integration Test: Audit logging functionality
     */
    public function test_audit_logging_functionality()
    {
        // Create a payment method
        $createData = [
            'name' => 'Audit Log Test',
            'provider' => 'stripe',
            'api_key' => 'pk_test_audit_123456789012345678901234567890',
            'secret_key' => 'sk_live_audit_123456789012345678901234567890',
            'mode' => 'test',
            'active' => true,
        ];

        $createResponse = $this->postJson('/admin/api/payment-methods', $createData);
        $createResponse->assertStatus(201);
        $methodId = $createResponse->json()['method']['id'];

        // Update the payment method
        $updateData = [
            'name' => 'Audit Log Test Updated',
            'provider' => 'stripe',
            'api_key' => 'pk_test_audit_updated_123456789012345678901234567890',
            'secret_key' => 'sk_live_audit_updated_123456789012345678901234567890',
            'mode' => 'live',
            'active' => false,
        ];

        $updateResponse = $this->putJson("/admin/api/payment-methods/{$methodId}", $updateData);
        $updateResponse->assertStatus(200);

        // View the payment method
        $viewResponse = $this->getJson("/admin/api/payment-methods/{$methodId}");
        $viewResponse->assertStatus(200);

        // Delete the payment method
        $deleteResponse = $this->deleteJson("/admin/api/payment-methods/{$methodId}");
        $deleteResponse->assertStatus(204);

        // Check if log file exists and contains entries
        $logPattern = storage_path('logs/payment_methods-*.log');
        $logFiles = glob($logPattern);
        $this->assertNotEmpty($logFiles, 'No payment methods log files found');

        $logFile = $logFiles[0]; // Get the most recent log file
        $this->assertFileExists($logFile);

        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('Payment method created', $logContent);
        $this->assertStringContainsString('Payment method updated', $logContent);
        $this->assertStringContainsString('Payment method accessed', $logContent);
        $this->assertStringContainsString('Payment method deleted', $logContent);

        // Verify log contains structured data
        $this->assertStringContainsString('user_id', $logContent);
        $this->assertStringContainsString('action', $logContent);
        $this->assertStringContainsString('timestamp', $logContent);
    }
}
