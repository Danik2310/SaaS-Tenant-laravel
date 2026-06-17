<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\TenantRepositoryInterface;
use App\Models\Tenant;

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
