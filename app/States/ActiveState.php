<?php

declare(strict_types=1);

namespace App\States;

class ActiveState extends TenantState
{
    public static function label(): string
    {
        return 'Active';
    }

    public static function allowedTransitions(): array
    {
        return ['Suspended', 'Deleted'];
    }
}
