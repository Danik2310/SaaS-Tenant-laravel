<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Tenant;
use Database\Seeders\CentralRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantPermissionIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private array $createdDatabases = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CentralRolePermissionSeeder::class);

        $admin = AdminUser::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin, 'admin');
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        foreach ($this->createdDatabases as $dbName) {
            try {
                DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
            } catch (\Exception) {
            }
        }

        parent::tearDown();
    }

    protected function createTestTenant(): Tenant
    {
        $tenant = Tenant::withoutEvents(function () {
            $t = Tenant::create(['id' => 'test-'.uniqid()]);
            $t->database()->makeCredentials();
            $t->database()->manager()->createDatabase($t);
            $t->save();
            $t->refresh();

            return $t;
        });

        $dbName = $tenant->database()->getName();
        $this->createdDatabases[] = $dbName;

        config([
            'database.connections.tenant' => [
                'driver' => env('DB_CONNECTION', 'mysql'),
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => $dbName,
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ],
        ]);

        DB::purge('tenant');

        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--force' => true,
        ]);

        return $tenant;
    }

    public function test_tenant_has_own_permission_tables(): void
    {
        $this->tenant = $this->createTestTenant();
        tenancy()->initialize($this->tenant);

        $tableNames = config('permission.table_names');

        $this->assertTrue(Schema::connection('tenant')->hasTable($tableNames['permissions']));
        $this->assertTrue(Schema::connection('tenant')->hasTable($tableNames['roles']));
        $this->assertTrue(Schema::connection('tenant')->hasTable($tableNames['model_has_permissions']));
        $this->assertTrue(Schema::connection('tenant')->hasTable($tableNames['model_has_roles']));
        $this->assertTrue(Schema::connection('tenant')->hasTable($tableNames['role_has_permissions']));
    }

    public function test_tenant_permission_tables_are_empty_by_default(): void
    {
        $this->tenant = $this->createTestTenant();
        tenancy()->initialize($this->tenant);

        $tableNames = config('permission.table_names');

        $this->assertEquals(0, DB::connection('tenant')->table($tableNames['permissions'])->count());
        $this->assertEquals(0, DB::connection('tenant')->table($tableNames['roles'])->count());
    }

    public function test_central_permissions_are_not_visible_in_tenant_db(): void
    {
        $this->tenant = $this->createTestTenant();
        tenancy()->initialize($this->tenant);

        $tableNames = config('permission.table_names');
        $tenantPermissions = DB::connection('tenant')->table($tableNames['permissions'])->pluck('name');

        $this->assertCount(0, $tenantPermissions);
    }

    public function test_cache_key_differs_per_tenant(): void
    {
        $tenantA = $this->createTestTenant();
        tenancy()->initialize($tenantA);
        $cacheKeyA = app(\Spatie\Permission\PermissionRegistrar::class)->getCacheKey();
        tenancy()->end();

        $tenantB = $this->createTestTenant();
        tenancy()->initialize($tenantB);
        $cacheKeyB = app(\Spatie\Permission\PermissionRegistrar::class)->getCacheKey();
        tenancy()->end();

        $this->assertNotEquals($cacheKeyA, $cacheKeyB);
    }

    public function test_central_cache_key_differs_from_tenant_cache_key(): void
    {
        $centralRegistrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $centralKey = $centralRegistrar->getCacheKey();

        $this->tenant = $this->createTestTenant();
        tenancy()->initialize($this->tenant);

        $tenantRegistrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $tenantKey = $tenantRegistrar->getCacheKey();
        tenancy()->end();

        $this->assertNotEquals($centralKey, $tenantKey);
    }

    public function test_cache_key_contains_tenant_id_when_initialized(): void
    {
        $this->tenant = $this->createTestTenant();
        tenancy()->initialize($this->tenant);

        $key = app(\Spatie\Permission\PermissionRegistrar::class)->getCacheKey();

        $this->assertStringContainsString('_tenant_', $key);
        $this->assertStringContainsString($this->tenant->id, $key);
        tenancy()->end();
    }

    public function test_cache_key_contains_central_suffix_when_not_in_tenant_context(): void
    {
        $key = app(\Spatie\Permission\PermissionRegistrar::class)->getCacheKey();

        $this->assertStringContainsString('_central', $key);
        $this->assertStringNotContainsString('_tenant_', $key);
    }
}
