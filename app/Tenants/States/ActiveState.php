<?php

declare(strict_types=1);

namespace App\Tenants\States;

/** Tenant is active and fully usable. */
class ActiveState extends TenantState
{
    public static function label(): string
    {
        return 'Active';
    }

    public static function allowedTransitions(): array
    {
        return ['Trial', 'Suspended', 'Deleted'];
    }
}
