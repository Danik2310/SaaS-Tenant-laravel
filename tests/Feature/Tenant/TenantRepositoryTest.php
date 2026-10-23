<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Plan;
use App\Models\Tenant;
use App\Tenants\Contracts\TenantRepositoryInterface;
use App\Tenants\Decorators\CachedTenantRepository;
use App\Tenants\Repositories\TenantRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TenantRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_by_id_eager_loads_plan_and_domains(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        $found = (new TenantRepository)->findById($tenant->id);

        $this->assertNotNull($found);
        $this->assertSame($tenant->id, $found->id);
        $this->assertTrue($found->relationLoaded('plan'));
        $this->assertTrue($found->relationLoaded('domains'));
    }

    public function test_find_by_id_includes_soft_deleted_tenants(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->delete();

        $found = (new TenantRepository)->findById($tenant->id);

        $this->assertNotNull($found);
        $this->assertTrue($found->trashed());
    }

    public function test_find_by_id_returns_null_for_an_unknown_tenant(): void
    {
        $this->assertNull((new TenantRepository)->findById('does-not-exist'));
    }

    public function test_find_by_domain_matches_an_attached_domain(): void
    {
        $tenant = Tenant::factory()->create();

        $found = (new TenantRepository)->findByDomain($tenant->id.'.sasapp');

        $this->assertNotNull($found);
        $this->assertSame($tenant->id, $found->id);
        $this->assertNull((new TenantRepository)->findByDomain('unknown.sasapp'));
    }

    public function test_cached_repository_only_queries_until_the_tenant_tag_is_flushed(): void
    {
        $tenant = Tenant::factory()->create();

        $inner = new class($tenant) implements TenantRepositoryInterface
        {
            public int $byIdCalls = 0;

            public function __construct(private readonly Tenant $tenant) {}

            public function findById(string $id): ?Tenant
            {
                $this->byIdCalls++;

                return $this->tenant;
            }

            public function findByDomain(string $domain): ?Tenant
            {
                return null;
            }
        };

        $repository = new CachedTenantRepository($inner);

        $this->assertSame($tenant->id, $repository->findById($tenant->id)?->id);
        $this->assertSame($tenant->id, $repository->findById($tenant->id)?->id);
        $this->assertSame(1, $inner->byIdCalls);

        Cache::tags(['tenant_'.$tenant->id])->flush();

        $repository->findById($tenant->id);
        $this->assertSame(2, $inner->byIdCalls);
    }

    public function test_cached_repository_delegates_domain_lookups_without_caching(): void
    {
        $tenant = Tenant::factory()->create();

        $inner = new class($tenant) implements TenantRepositoryInterface
        {
            public int $byDomainCalls = 0;

            public function __construct(private readonly Tenant $tenant) {}

            public function findById(string $id): ?Tenant
            {
                return null;
            }

            public function findByDomain(string $domain): ?Tenant
            {
                $this->byDomainCalls++;

                return $this->tenant;
            }
        };

        $repository = new CachedTenantRepository($inner);

        $repository->findByDomain($tenant->id.'.sasapp');
        $repository->findByDomain($tenant->id.'.sasapp');

        $this->assertSame(2, $inner->byDomainCalls);
    }
}
