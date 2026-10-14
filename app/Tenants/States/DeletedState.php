<?php

declare(strict_types=1);

namespace App\Tenants\States;

/** Tenant is soft-deleted; its database has been dropped and it can be restored. */
class DeletedState extends TenantState
{
    public static function label(): string
    {
        return 'Deleted';
    }

    public static function allowedTransitions(): array
    {
        return ['Active'];
    }
}
