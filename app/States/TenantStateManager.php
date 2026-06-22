<?php

declare(strict_types=1);

namespace App\States;

use App\Events\TenantReactivated;
use App\Events\TenantSuspended;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
    }

    public static function flushTenantCache(Tenant $tenant): void
    {
        // IMPORTANT: Do NOT call tenancy()->initialize() here. When this is called after
        // $tenant->delete(), the tenant's database has already been dropped, so
        // DatabaseTenancyBootstrapper::bootstrap() will throw
        // TenantDatabaseDoesNotExistException. The subsequent tenancy()->end() triggers
        // RevertToCentralContext -> CacheTenancyBootstrapper::revert(), which corrupts the
        // cache manager because bootstrap() was never called (the Guarded method).
        // Instead, flush the tenant's cache tags directly without initializing tenancy.

        try {
            Cache::tags([config('tenancy.cache.tag_base').$tenant->getTenantKey()])->flush();
        } catch (\BadMethodCallException $e) {
            try {
                Cache::flush();
            } catch (\Throwable $inner) {
                Log::warning('Could not flush cache for tenant '.$tenant->id.': '.$inner->getMessage());
            }
        } catch (\Throwable $e) {
            Log::warning('Could not flush cache for tenant '.$tenant->id.': '.$e->getMessage());
        }
    }
}
