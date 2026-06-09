<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Plan;
use App\Models\Tenant;

interface TenantManagerInterface
{
    public function provision(array $data): Tenant;

    public function suspend(Tenant $tenant): void;

    public function activate(Tenant $tenant): void;

    public function delete(Tenant $tenant): void;

    public function restore(Tenant $tenant): void;

    public function changePlan(Tenant $tenant, Plan $newPlan): void;
}
