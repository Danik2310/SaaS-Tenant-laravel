<?php

namespace App\Shared\Services;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\PermissionRegistrar as SpatiePermissionRegistrar;

/**
 * Tenant-scoped Spatie permission cache registrar.
 *
 * Spatie caches the permission/role tables under a single key; this subclass
 * suffixes that key with `_central` or `_tenant_<id>` so one tenant's cached
 * permissions can never be read while another tenant (or central context) is
 * active.
 */
class TenantAwarePermissionRegistrar extends SpatiePermissionRegistrar
{
    private function getTenantScopeSuffix(): string
    {
        if (tenancy()->initialized) {
            return '_tenant_'.tenant('id');
        }

        return '_central';
    }

    private function withScopedCacheKey(callable $fn): mixed
    {
        $originalKey = $this->cacheKey;
        $this->cacheKey = $originalKey.$this->getTenantScopeSuffix();

        try {
            return $fn();
        } finally {
            $this->cacheKey = $originalKey;
        }
    }

    /** {@inheritdoc} — scoped to the current tenancy context. */
    public function getPermissions(array $params = [], bool $onlyOne = false): Collection
    {
        return $this->withScopedCacheKey(fn () => parent::getPermissions($params, $onlyOne));
    }

    /** Forget the cached permissions for the current tenancy context only. */
    public function forgetCachedPermissions()
    {
        return $this->withScopedCacheKey(fn () => parent::forgetCachedPermissions());
    }

    /** The tenant-scoped cache key. */
    public function getCacheKey(): string
    {
        return $this->cacheKey.$this->getTenantScopeSuffix();
    }
}
