<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class TenantTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Test tenant instance.
     */
    protected Tenant $testTenant;

    /**
     * Track created databases for cleanup.
     */
    protected array $createdDatabases = [];

    /**
     * Setup for each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create and initialize test tenant
        $this->testTenant = $this->createTestTenant();
        $this->createdDatabases[] = $this->testTenant->database()->getName();
        $this->initializeTenant($this->testTenant);
    }

    /**
     * Tear down for each test.
     */
    protected function tearDown(): void
    {
        // Clean up databases
        $this->forgetTenant();

        foreach ($this->createdDatabases as $dbName) {
            try {
                DB::statement("DROP DATABASE IF EXISTS `$dbName`");
            } catch (\Exception $e) {
                // Ignore if fails
            }
        }

        parent::tearDown();
    }
}
