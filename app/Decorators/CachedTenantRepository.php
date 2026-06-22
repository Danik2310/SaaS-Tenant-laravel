<?php

declare(strict_types=1);

namespace App\Decorators;

use App\Contracts\TenantRepositoryInterface;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class CachedTenantRepository implements TenantRepositoryInterface
{
    public function __construct(
        private TenantRepositoryInterface $inner,
    ) {}

    public function findById(string $id): ?Tenant
    {
        try {
            return Cache::tags(['tenant_'.$id])->remember("tenant:{$id}", 3600, function () use ($id) {
                return $this->inner->findById($id);
            });
        } catch (\BadMethodCallException) {
            return $this->inner->findById($id);
        }
    }

    public function findByDomain(string $domain): ?Tenant
    {
        return $this->inner->findByDomain($domain);
    }
}
