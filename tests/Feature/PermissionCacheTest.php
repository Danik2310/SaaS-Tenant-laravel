<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Tenant;
use App\Shared\Services\TenantAwarePermissionRegistrar;
use Database\Seeders\CentralRolePermissionSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionCacheTest extends TestCase
{
    use RefreshDatabase;

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
        $tenant = parent::createTestTenant();
        $this->createdDatabases[] = $tenant->database()->getName();

        return $tenant;
    }

    public function test_registrar_is_tenant_aware_instance(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $this->assertInstanceOf(TenantAwarePermissionRegistrar::class, $registrar);
    }

    public function test_central_cache_key_starts_with_default_and_ends_with_central(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $key = $registrar->getCacheKey();

        $this->assertStringStartsWith('spatie.permission.cache', $key);
        $this->assertStringEndsWith('_central', $key);
    }

    public function test_central_cache_key_is_stable_across_calls(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $key1 = $registrar->getCacheKey();
        $key2 = $registrar->getCacheKey();

        $this->assertEquals($key1, $key2);
    }

    public function test_tenant_cache_key_includes_tenant_id(): void
    {
        $tenant = $this->createTestTenant();
        tenancy()->initialize($tenant);

        $registrar = app(PermissionRegistrar::class);
        $key = $registrar->getCacheKey();

        $this->assertStringContainsString('_tenant_', $key);
        $this->assertStringContainsString($tenant->id, $key);
        $this->assertStringNotContainsString('_central', $key);

        tenancy()->end();
    }

    public function test_cache_key_switches_between_central_and_tenant(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $centralKey = $registrar->getCacheKey();

        $tenant = $this->createTestTenant();
        tenancy()->initialize($tenant);
        $tenantKey = $registrar->getCacheKey();
        tenancy()->end();

        $this->assertNotEquals($centralKey, $tenantKey);
    }

    public function test_cache_key_is_different_per_tenant(): void
    {
        $tenantA = $this->createTestTenant();
        tenancy()->initialize($tenantA);
        $keyA = app(PermissionRegistrar::class)->getCacheKey();
        tenancy()->end();

        $tenantB = $this->createTestTenant();
        tenancy()->initialize($tenantB);
        $keyB = app(PermissionRegistrar::class)->getCacheKey();
        tenancy()->end();

        $this->assertNotEquals($keyA, $keyB);
    }

    public function test_get_permissions_uses_scoped_cache_key(): void
    {
        $tenant = $this->createTestTenant();
        tenancy()->initialize($tenant);

        $registrar = app(PermissionRegistrar::class);
        $permissions = $registrar->getPermissions();

        $this->assertInstanceOf(Collection::class, $permissions);
        $this->assertCount(0, $permissions);

        tenancy()->end();
    }

    public function test_forget_cached_permissions_clears_scoped_cache(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $key = $registrar->getCacheKey();

        Cache::store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forever($key, ['cached-data']);

        $this->assertNotNull(Cache::get($key));

        $registrar->forgetCachedPermissions();

        $this->assertNull(Cache::get($key));
    }

    public function test_clear_permissions_collection_clears_in_memory(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $permissions = $registrar->getPermissions();
        $this->assertGreaterThan(0, $permissions->count());

        $registrar->clearPermissionsCollection();

        $prop = new \ReflectionProperty($registrar, 'permissions');
        $prop->setAccessible(true);
        $this->assertNull($prop->getValue($registrar));

        $reloaded = $registrar->getPermissions();
        $this->assertGreaterThan(0, $reloaded->count());
        $this->assertEquals($permissions->count(), $reloaded->count());
    }
}
