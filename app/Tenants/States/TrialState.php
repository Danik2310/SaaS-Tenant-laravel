<?php

declare(strict_types=1);

namespace App\Tenants\States;

class TrialState extends TenantState
{
    public static function label(): string
    {
        return 'Trial';
    }

    public static function allowedTransitions(): array
    {
        return ['Active', 'Suspended', 'Cancelled', 'Deleted'];
    }
}
