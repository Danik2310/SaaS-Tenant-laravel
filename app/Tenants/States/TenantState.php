<?php

declare(strict_types=1);

namespace App\Tenants\States;

/**
 * Base class for the tenant lifecycle states.
 *
 * Each concrete state (Active, Trial, Suspended, Cancelled, Deleted) declares a
 * human label and the transitions it allows. See {@see TenantStateManager} for
 * the machine itself.
 */
abstract class TenantState
{
    /** Human-readable label for this state (matches the tenant `status` value). */
    abstract public static function label(): string;

    /** @return list<string> statuses reachable from this state */
    abstract public static function allowedTransitions(): array;
}
