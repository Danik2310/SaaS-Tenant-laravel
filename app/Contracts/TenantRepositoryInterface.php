<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Tenant;

interface TenantRepositoryInterface
{
    public function findById(string $id): ?Tenant;

    public function findByDomain(string $domain): ?Tenant;
}
