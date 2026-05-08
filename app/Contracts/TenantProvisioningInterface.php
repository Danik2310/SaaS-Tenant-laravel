<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Tenant;

interface TenantProvisioningInterface
{
    public function provision(array $data): Tenant;
}
