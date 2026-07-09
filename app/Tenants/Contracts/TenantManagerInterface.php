<?php

declare(strict_types=1);

namespace App\Tenants\Contracts;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\Carbon;

interface TenantManagerInterface
{
    public function provision(array $data): Tenant;

    public function suspend(Tenant $tenant): void;

    public function activate(Tenant $tenant): void;

    public function delete(Tenant $tenant): void;

    public function restore(Tenant $tenant): void;

    public function changePlan(Tenant $tenant, Plan $newPlan): void;

    public function createSubscription(Tenant $tenant, Plan $plan, string $status, ?Carbon $endsAt = null, ?Carbon $startsAt = null): Subscription;

    public function setStatus(Tenant $tenant, string $status): void;

    public function extendTrial(Tenant $tenant, int $days): void;

    public function migrateTenant(Tenant $tenant): array;
}
