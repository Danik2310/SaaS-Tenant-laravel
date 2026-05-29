<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\PermissionRegistrar as SpatiePermissionRegistrar;

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

    public function getPermissions(array $params = [], bool $onlyOne = false): Collection
    {
        return $this->withScopedCacheKey(fn () => parent::getPermissions($params, $onlyOne));
    }

    public function forgetCachedPermissions()
    {
        return $this->withScopedCacheKey(fn () => parent::forgetCachedPermissions());
    }

    public function getCacheKey(): string
    {
        return $this->cacheKey.$this->getTenantScopeSuffix();
    }
}
