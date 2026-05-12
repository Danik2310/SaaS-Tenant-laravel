<?php

namespace Tests\Unit\Observers;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class ObserverTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Primary tenant instance used throughout the test.
     */
    protected Tenant $tenant;

    /**
     * Track tenant database names for cleanup in tearDown().
     *
     * @var array<int, string>
     */
    protected array $createdDatabases = [];

    /**
     * Setup: create a test tenant, run migrations, and initialize tenancy.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        $this->createdDatabases[] = $this->tenant->database()->getName();
        $this->initializeTenant($this->tenant);
    }

    /**
     * Tear down: end tenancy and drop all tenant databases created during the test.
     */
    protected function tearDown(): void
    {
        $this->forgetTenant();

        foreach ($this->createdDatabases as $dbName) {
            try {
                DB::statement("DROP DATABASE IF EXISTS `$dbName`");
            } catch (\Exception $e) {
                // Silently ignore — the DB may already be gone or inaccessible
            }
        }

        parent::tearDown();
    }
}
