<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Tenants\Services\DatabaseService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseServiceTest extends TestCase
{
    private string $schema = 'tenanttest-drop-target';

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('mysql_central')->statement("CREATE DATABASE IF NOT EXISTS `{$this->schema}`");
    }

    protected function tearDown(): void
    {
        try {
            DB::connection('mysql_central')->statement("DROP DATABASE IF EXISTS `{$this->schema}`");
            DB::connection('mysql_central')->statement('DROP DATABASE IF EXISTS `tenanttest-quo``te`');
        } catch (\Throwable) {
            // Left for TestCase's orphan sweep.
        }

        parent::tearDown();
    }

    public function test_drop_database_is_idempotent(): void
    {
        $service = new DatabaseService;

        $this->assertTrue($service->databaseExists($this->schema));

        $service->dropDatabase($this->schema);

        $this->assertFalse($service->databaseExists($this->schema));

        // Re-running must not throw "Unknown database", which is what a bare
        // DROP DATABASE would raise after the first drop.
        $service->dropDatabase($this->schema);
        $service->dropDatabase($this->schema);

        $this->assertFalse($service->databaseExists($this->schema));
    }

    public function test_drop_database_is_a_no_op_for_a_schema_that_never_existed(): void
    {
        $service = new DatabaseService;
        $missing = 'tenanttest-never-created';

        $service->dropDatabase($missing);

        $this->assertFalse($service->databaseExists($missing));
    }

    public function test_drop_database_quotes_identifiers_containing_backticks(): void
    {
        $schema = 'tenanttest-quo`te';

        DB::connection('mysql_central')->statement('CREATE DATABASE IF NOT EXISTS `tenanttest-quo``te`');

        $this->assertTrue((new DatabaseService)->databaseExists($schema));

        (new DatabaseService)->dropDatabase($schema);

        $this->assertFalse((new DatabaseService)->databaseExists($schema));
    }
}
