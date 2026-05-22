<?php

declare(strict_types=1);

namespace App\States;

class TrialState extends TenantState
{
    public static function label(): string
    {
        return 'Trial';
    }

    public static function allowedTransitions(): array
    {
        return ['Active', 'Suspended', 'Deleted'];
    }
}
