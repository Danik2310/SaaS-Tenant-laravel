<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Tenants\Listeners\CreateTenantStorageSkeleton;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\TestCase;

class CreateTenantStorageSkeletonTest extends TestCase
{
    public function test_listener_creates_the_tenant_storage_skeleton(): void
    {
        $tenant = new Tenant(['id' => 'test-storage-skeleton']);

        (new CreateTenantStorageSkeleton)->handle(new TenantCreated($tenant));

        $base = storage_path(config('tenancy.filesystem.suffix_base').$tenant->getTenantKey());

        $this->assertDirectoryExists($base);
        $this->assertDirectoryExists($base.'/app');
        $this->assertDirectoryExists($base.'/app/public');

        rmdir($base.'/app/public');
        rmdir($base.'/app');
        rmdir($base);
    }
}
