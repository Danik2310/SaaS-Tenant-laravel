<?php

namespace App\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Alias: `session.expiry`.
 *
 * Enforces an absolute session lifetime on top of Laravel's idle timeout.
 *
 * Flow:
 *  1. No authenticated user on the guard → continue.
 *  2. If `now − last_activity` exceeds `session.absolute_lifetime` (minutes),
 *     log the guard out and return a guard-aware response — 401 JSON, or a
 *     redirect to `central.login` for the admin guard and `login` otherwise.
 *  3. Otherwise refresh `last_activity` and continue.
 */
class EnsureSessionNotExpired
{
    public function handle(Request $request, Closure $next, ?string $guard = null)
    {
        $guardName = $guard ?: $this->getDefaultGuard();
        $user = Auth::guard($guardName)->user();

        if (! $user) {
            return $next($request);
        }

        $absoluteLifetime = (int) config('session.absolute_lifetime', 720);
        $lastActivity = session('last_activity');

        if ($lastActivity && (now()->timestamp - $lastActivity) > ($absoluteLifetime * 60)) {
            Auth::guard($guardName)->logout();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expired.'], 401);
            }

            return redirect()->route($guardName === 'admin' ? 'central.login' : 'login');
        }

        session(['last_activity' => now()->timestamp]);

        return $next($request);
    }

    protected function getDefaultGuard(): string
    {
        return config('auth.defaults.guard', 'web');
    }
}
