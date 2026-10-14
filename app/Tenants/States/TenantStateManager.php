<?php

declare(strict_types=1);

namespace App\Tenants\States;

use App\Models\Tenant;
use App\Tenants\Events\TenantReactivated;
use App\Tenants\Events\TenantSuspended;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Tenant lifecycle state machine.
 *
 * Every status maps to a {@see TenantState} subclass that declares its allowed
 * transitions. All changes go through {@see transitionTo()}, which fires
 * {@see TenantSuspended} / {@see TenantReactivated} as side effects.
 *
 * Transition table:
 *   Active    → Suspended, Deleted
 *   Trial     → Active, Suspended, Cancelled, Deleted
 *   Suspended → Active, Deleted
 *   Cancelled → Active, Deleted
 *   Deleted   → Active
 */
class TenantStateManager
{
    /** @return list<string> the statuses reachable from `$fromStatus` (empty when unknown) */
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

    /**
     * Move a tenant to `$targetStatus`, firing {@see TenantSuspended} and
     * {@see TenantReactivated} as side effects.
     *
     * @throws InvalidArgumentException when the transition is not allowed
     */
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
    }

    /**
     * Flush the tenant's cache tags **without** initialising tenancy (see the
     * inline note: re-initialising after the database is dropped corrupts the
     * cache manager). Never touches global cache, even on untaggable stores.
     */
    public static function flushTenantCache(Tenant $tenant): void
    {
        // IMPORTANT: Do NOT call tenancy()->initialize() here. When this is called after
        // $tenant->delete(), the tenant's database has already been dropped, so
        // DatabaseTenancyBootstrapper::bootstrap() will throw
        // TenantDatabaseDoesNotExistException. The subsequent tenancy()->end() triggers
        // RevertToCentralContext -> CacheTenancyBootstrapper::revert(), which corrupts the
        // cache manager because bootstrap() was never called (the Guarded method).
        // Instead, flush the tenant's cache tags directly without initializing tenancy.
        //
        // When the cache driver does not support tags (e.g. file, array), we skip the
        // flush entirely rather than falling back to Cache::flush() which would destroy
        // ALL cached data including global settings, plan lists, and other tenants' data.
        // The data will be re-cached on the next request.

        try {
            Cache::tags(['tenant_'.$tenant->getTenantKey()])->flush();
        } catch (\BadMethodCallException) {
            // Cache driver does not support tags — acceptable, data will be re-cached
        } catch (\Throwable $e) {
            Log::warning('Could not flush cache for tenant '.$tenant->id.': '.$e->getMessage());
        }
    }
}
