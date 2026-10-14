<?php

declare(strict_types=1);

namespace App\Tenants\States;

/** Tenant is in a time-limited trial period. */
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
