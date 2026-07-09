<?php

declare(strict_types=1);

namespace App\Tenants\States;

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
