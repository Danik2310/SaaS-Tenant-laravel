<?php

declare(strict_types=1);

namespace App\Tenants\States;

class SuspendedState extends TenantState
{
    public static function label(): string
    {
        return 'Suspended';
    }

    public static function allowedTransitions(): array
    {
        return ['Active', 'Deleted'];
    }
}
