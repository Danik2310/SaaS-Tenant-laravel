<?php

declare(strict_types=1);

namespace App\Tenants\States;

use App\Models\Tenant;
use App\Shared\Contracts\TenantLifecycleInterface;

/**
 * {@see TenantLifecycleInterface} implementation backed by the tenant state
 * machine, so other modules can suspend tenants without importing it.
 */
class TenantLifecycle implements TenantLifecycleInterface
{
    public function suspend(Tenant $tenant): void
    {
        TenantStateManager::transitionTo($tenant, 'Suspended');
    }

    public function flushCache(Tenant $tenant): void
    {
        TenantStateManager::flushTenantCache($tenant);
    }
}
