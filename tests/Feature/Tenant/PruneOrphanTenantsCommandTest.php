<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Tenants\Contracts\DatabaseServiceInterface;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `tenants:prune-orphans` decides which schemas and rows are drift; the tests
 * here pin those decisions down without ever issuing a real DROP DATABASE —
 * DatabaseServiceInterface is mocked so `--force` cannot touch schemas this
 * test does not own (the dev database carries orphan schemas left by older runs).
 */
class PruneOrphanTenantsCommandTest extends TestCase
{
    /** @var list<string> */
    private array $schemasToDrop = [];

    protected function tearDown(): void
    {
        foreach ($this->schemasToDrop as $name) {
            try {
                DB::connection('mysql_central')->statement("DROP DATABASE IF EXISTS `{$name}`");
            } catch (\Throwable) {
                // A locked schema is left for TestCase's orphan sweep.
            }
        }

        $this->schemasToDrop = [];

        parent::tearDown();
    }

    public function test_defaults_to_report_only_mode(): void
    {
        $orphan = $this->createSchemaWithoutTenant('report-only');

        $this->mock(DatabaseServiceInterface::class, function ($mock): void {
            $mock->shouldNotReceive('dropDatabase');
        });

        $this->artisan('tenants:prune-orphans')
            ->expectsOutputToContain('Report-only mode')
            ->assertExitCode(0);

        $this->assertTrue($this->schemaExists($orphan));
    }

    public function test_force_drops_orphan_schemas_but_never_schemas_with_a_tenant_row(): void
    {
        $orphan = $this->createSchemaWithoutTenant('orphan');
        $withLiveRow = $this->createSchemaWithoutTenant('keep-live');
        $withTrashedRow = $this->createSchemaWithoutTenant('keep-trashed');

        Tenant::withoutEvents(fn (): Tenant => Tenant::create([
            'id' => 'test-keep-live',
        ]));

        $trashed = Tenant::withoutEvents(fn (): Tenant => Tenant::create([
            'id' => 'test-keep-trashed',
        ]));

        Tenant::withoutEvents(fn () => $trashed->forceFill(['deleted_at' => now()])->save());

        $dropped = [];
        $this->mock(DatabaseServiceInterface::class, function ($mock) use (&$dropped): void {
            $mock->shouldReceive('dropDatabase')
                ->atLeast()->once()
                ->andReturnUsing(function (string $name) use (&$dropped): void {
                    $dropped[] = $name;
                });
        });

        $this->artisan('tenants:prune-orphans', ['--force' => true])
            ->assertExitCode(0);

        $this->assertContains($orphan, $dropped);
        $this->assertNotContains($withLiveRow, $dropped, 'a schema with a live tenant row must be spared');
        $this->assertNotContains($withTrashedRow, $dropped, 'a soft-deleted tenant keeps its schema for restore');
    }

    public function test_delete_rows_option_is_ignored_without_force(): void
    {
        $tenantId = 'test-orphan-row';
        Tenant::withoutEvents(fn (): Tenant => Tenant::create(['id' => $tenantId]));

        $this->mock(DatabaseServiceInterface::class, function ($mock): void {
            $mock->shouldNotReceive('dropDatabase');
        });

        $this->artisan('tenants:prune-orphans', ['--delete-rows' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('tenants', ['id' => $tenantId]);
    }

    public function test_force_with_delete_rows_removes_rows_that_have_no_schema(): void
    {
        $tenantId = 'test-orphan-row';
        Tenant::withoutEvents(fn (): Tenant => Tenant::create(['id' => $tenantId]));

        $this->mock(DatabaseServiceInterface::class, function ($mock): void {
            $mock->shouldReceive('dropDatabase')->atLeast()->once()->andReturnNull();
        });

        $this->artisan('tenants:prune-orphans', [
            '--force' => true,
            '--delete-rows' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('tenants', ['id' => $tenantId]);
    }

    private function createSchemaWithoutTenant(string $slug): string
    {
        $name = 'tenanttest-'.$slug;

        DB::connection('mysql_central')->statement("CREATE DATABASE IF NOT EXISTS `{$name}`");

        $this->schemasToDrop[] = $name;

        return $name;
    }

    private function schemaExists(string $name): bool
    {
        return (bool) DB::connection('mysql_central')
            ->table('information_schema.schemata')
            ->where('SCHEMA_NAME', $name)
            ->exists();
    }
}
