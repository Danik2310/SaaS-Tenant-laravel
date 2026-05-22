<?php

declare(strict_types=1);

namespace App\States;

use App\Events\TenantReactivated;
use App\Events\TenantSuspended;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class TenantStateManager
{
    public static function allowedTransitions(string $fromStatus): array
    {
        return match ($fromStatus) {
            'Active' => ActiveState::allowedTransitions(),
            'Trial' => TrialState::allowedTransitions(),
            'Suspended' => SuspendedState::allowedTransitions(),
            'Cancelled' => CancelledState::allowedTransitions(),
            'Deleted' => DeletedState::allowedTransitions(),
            default => [],
        };
    }

    public static function transitionTo(Tenant $tenant, string $targetStatus): void
    {
        $allowed = self::allowedTransitions($tenant->status);

        if (! in_array($targetStatus, $allowed, true)) {
            throw new InvalidArgumentException(sprintf(
                "Cannot transition tenant '%s' from '%s' to '%s'. Allowed transitions: %s",
                $tenant->id,
                $tenant->status,
                $targetStatus,
                implode(', ', $allowed) ?: 'none'
            ));
        }

        $oldStatus = $tenant->status;

        $tenant->status = $targetStatus;
        $tenant->save();

        if ($targetStatus === 'Suspended') {
            event(new TenantSuspended($tenant));
        }

        if ($targetStatus === 'Active' && $oldStatus !== 'Active') {
            event(new TenantReactivated($tenant));
        }

        Cache::tags(['tenant_'.$tenant->id])->flush();
    }
}
