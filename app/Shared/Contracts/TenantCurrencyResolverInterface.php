<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface TenantCurrencyResolverInterface
{
    /**
     * Tenant storefront currency from the current tenant's settings.
     * Returns null outside tenant context or when the setting is absent.
     */
    public function currentCode(): ?string;
}
