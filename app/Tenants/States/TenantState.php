<?php

declare(strict_types=1);

namespace App\Tenants\States;

abstract class TenantState
{
    abstract public static function label(): string;

    abstract public static function allowedTransitions(): array;
}
