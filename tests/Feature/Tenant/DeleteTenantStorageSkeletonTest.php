<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Tenants\Listeners\DeleteTenantStorageSkeleton;
use Illuminate\Support\Facades\File;
use ReflectionProperty;
use Stancl\Tenancy\Events\TenantDeleted;
use Tests\TestCase;

class DeleteTenantStorageSkeletonTest extends TestCase
{
    private ?string $skeletonPath = null;

    protected function tearDown(): void
    {
        if ($this->skeletonPath !== null && is_dir($this->skeletonPath)) {
            File::deleteDirectory($this->skeletonPath);
        }

        $this->skeletonPath = null;

        parent::tearDown();
    }

    public function test_soft_delete_leaves_the_storage_skeleton_intact(): void
    {
        $tenant = $this->tenantWithSkeleton();

        (new DeleteTenantStorageSkeleton)->handle(new TenantDeleted($tenant));

        $this->assertDirectoryExists((string) $this->skeletonPath);
        $this->assertFileExists($this->skeletonPath.'/index.html');
    }

    public function test_force_delete_removes_the_storage_skeleton(): void
    {
        $tenant = $this->tenantWithSkeleton();
        $this->markAsForceDeleting($tenant);

        (new DeleteTenantStorageSkeleton)->handle(new TenantDeleted($tenant));

        $this->assertDirectoryDoesNotExist((string) $this->skeletonPath);
    }

    public function test_force_delete_without_a_skeleton_directory_does_not_throw(): void
    {
        $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::create(['id' => 'test-no-skeleton']));
        $this->markAsForceDeleting($tenant);

        (new DeleteTenantStorageSkeleton)->handle(new TenantDeleted($tenant));

        $this->assertDirectoryDoesNotExist(storage_path(config('tenancy.filesystem.suffix_base').$tenant->getTenantKey()));
    }

    private function tenantWithSkeleton(): Tenant
    {
        $tenant = Tenant::withoutEvents(
            fn (): Tenant => Tenant::create(['id' => 'test-storage-'.uniqid()])
        );

        $this->skeletonPath = storage_path(config('tenancy.filesystem.suffix_base').$tenant->getTenantKey());

        File::ensureDirectoryExists($this->skeletonPath);
        File::put($this->skeletonPath.'/index.html', 'keep me');

        return $tenant;
    }

    private function markAsForceDeleting(Tenant $tenant): void
    {
        $property = new ReflectionProperty($tenant, 'forceDeleting');
        $property->setAccessible(true);
        $property->setValue($tenant, true);
    }
}
