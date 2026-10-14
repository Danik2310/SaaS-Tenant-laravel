<?php

namespace App\Shared\Middleware;

use App\Shared\Support\ImpersonationState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Alias: `impersonation.expiry`.
 *
 * Clears an expired god-mode session on the central/admin side — the mirror of
 * the tenant-side check in {@see EnsureImpersonationValid}.
 *
 * Flow:
 *  1. Read the impersonation start time from the session.
 *  2. If it is older than `impersonation.ttl` minutes, clear it and log.
 *  3. Continue.
 */
class CheckImpersonationExpiry
{
    public function handle(Request $request, Closure $next)
    {
        $impersonating = session('impersonate_tenant');
        $startedAt = session('impersonate_started_at');

        if ($impersonating && $startedAt) {
            $ttl = config('impersonation.ttl', 60);
            $expiresAt = $startedAt + ($ttl * 60);

            if (now()->timestamp > $expiresAt) {
                ImpersonationState::clear();

                activity('impersonation')
                    ->causedBy(Auth::guard('admin')->user())
                    ->log('Impersonation session expired');
            }
        }

        return $next($request);
    }
}
