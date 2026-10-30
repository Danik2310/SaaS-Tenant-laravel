<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use App\Models\Tenant;

/**
 * Cross-module entry point for tenant lifecycle transitions.
 *
 * Other modules (billing, products, …) must depend on this contract instead of
 * importing the concrete `App\Tenants\States\TenantStateManager`.
 */
interface TenantLifecycleInterface
{
    /**
     * Move the tenant to the `Suspended` state.
     *
     * @throws \InvalidArgumentException when the transition is not allowed
     */
    public function suspend(Tenant $tenant): void;

    /**
     * Flush the tenant's cache tags **without** initialising tenancy.
     *
     * Safe to call even after the tenant's database has been dropped.
     */
    public function flushCache(Tenant $tenant): void;
}
