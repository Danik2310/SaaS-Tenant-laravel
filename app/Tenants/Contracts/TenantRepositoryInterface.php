<?php

declare(strict_types=1);

namespace App\Tenants\Contracts;

use App\Models\Tenant;

interface TenantRepositoryInterface
{
    public function findById(string $id): ?Tenant;

    public function findByDomain(string $domain): ?Tenant;
}
