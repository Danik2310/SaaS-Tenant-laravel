<?php

declare(strict_types=1);

namespace App\Tenants\Repositories;

use App\Models\Tenant;
use App\Tenants\Contracts\TenantRepositoryInterface;

class TenantRepository implements TenantRepositoryInterface
{
    public function findById(string $id): ?Tenant
    {
        return Tenant::withTrashed()->with(['plan', 'domains'])->find($id);
    }

    public function findByDomain(string $domain): ?Tenant
    {
        return Tenant::withTrashed()->with(['plan', 'domains'])
            ->whereHas('domains', fn ($q) => $q->where('domain', $domain))
            ->first();
    }
}
