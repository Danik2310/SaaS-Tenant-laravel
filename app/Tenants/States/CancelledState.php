<?php

declare(strict_types=1);

namespace App\Tenants\States;

class CancelledState extends TenantState
{
    public static function label(): string
    {
        return 'Cancelled';
    }

    public static function allowedTransitions(): array
    {
        return ['Active', 'Deleted'];
    }
}
