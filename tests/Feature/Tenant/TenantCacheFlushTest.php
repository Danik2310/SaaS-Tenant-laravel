<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the cache-tag contract between stancl/tenancy's automatic per-tenant
 * tag (config('tenancy.cache.tag_base').$id) and the literal 'tenant_'.$id tag
 * used by TenantStateManager::flushTenantCache() and the tenant listeners.
 *
 * If the two diverge, tenant-context cache entries are never flushed on
 * suspend / reactivate / plan change and stale tenant data survives.
 */
class TenantCacheFlushTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        $this->initializeTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        $this->forgetTenant();

        if (isset($this->tenant)) {
            DB::statement("DROP DATABASE IF EXISTS `{$this->tenant->database()->getName()}`");
        }

        parent::tearDown();
    }

    public function test_package_tenant_tag_matches_the_flush_tenant_cache_tag(): void
    {
        // stancl applies this tag to every cache call made in tenant context.
        $autoTag = config('tenancy.cache.tag_base').tenant()->getTenantKey();

        // flushTenantCache() flushes this literal tag from the central context.
        $this->assertSame('tenant_'.$this->tenant->getTenantKey(), $autoTag);
    }

    public function test_tenant_context_writes_are_cleared_by_the_tenant_tag_flush(): void
    {
        Cache::put('probe', 'value', 60);
        $this->assertSame('value', Cache::get('probe'));

        // Reproduce TenantStateManager::flushTenantCache()'s central-context
        // flush on the same store instance — the array store is not shared
        // across the tenancy cache-manager swap, so we flush the tag directly.
        Cache::store('array')->tags(['tenant_'.$this->tenant->getTenantKey()])->flush();

        $this->assertNull(Cache::get('probe'));
    }
}
