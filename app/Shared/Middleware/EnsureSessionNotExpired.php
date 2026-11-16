<?php

namespace App\Shared\Middleware;

use App\Shared\Support\AuthSession;
use App\Shared\Support\JwtCookie;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Alias: `session.expiry`.
 *
 * Enforces the two server-side session clocks on every authenticated request:
 *
 *  1. **Idle** — more than `session.idle_lifetime` minutes (default 30) since
 *     the last authenticated request.
 *  2. **Absolute** — more than `session.absolute_lifetime` minutes (default
 *     720) since login, regardless of activity.
 *
 * Both anchors are written at login by {@see AuthSession}. A request that
 * carries a token but no anchors is treated as expired, so an expired Laravel
 * session cannot be bypassed by a still-refreshable JWT.
 *
 * Must run **before** `jwt.refresh`: otherwise an idle-expired token is renewed
 * moments before the check and the session slides indefinitely. Impersonation
 * is skipped — god mode has its own `impersonation.ttl`.
 *
 * Flow:
 *  1. Impersonation session or no token → continue.
 *  2. Idle or absolute clock passed (or anchors missing) → blacklist the JWT,
 *     invalidate the session, expire the cookie, and return 401 JSON or a
 *     guard-aware redirect (`central.login` for `admin`, `login` otherwise).
 *  3. Otherwise stamp `last_activity` and continue.
 */
class EnsureSessionNotExpired
{
    public function handle(Request $request, Closure $next, ?string $guard = null)
    {
        $guardName = $guard ?: $this->getDefaultGuard();

        // God mode is bounded by `impersonation.ttl`, not the login clocks.
        if ($request->session()->has('impersonation')) {
            return $next($request);
        }

        // No credential at all means a guest; `auth` decides what happens next.
        if (! $this->hasToken($request)) {
            return $next($request);
        }

        if (AuthSession::expired()) {
            return $this->expire($request, $guardName);
        }

        AuthSession::touch();

        return $next($request);
    }

    /**
     * Revoke the token and the session, then send the user back to sign in.
     */
    protected function expire(Request $request, string $guardName)
    {
        // Blacklists the JWT; a no-op when the token is already expired or
        // blacklisted (both throw a JWTException that the guard swallows).
        Auth::guard($guardName)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()
                ->json(['message' => 'Session expired. Please sign in again.'], 401)
                ->withCookie(JwtCookie::forget());
        }

        return redirect()
            ->route($guardName === 'admin' ? 'central.login' : 'login')
            ->withCookie(JwtCookie::forget());
    }

    /**
     * Whether the request carries a bearer token (header or JWT cookie).
     */
    protected function hasToken(Request $request): bool
    {
        return $request->headers->has('Authorization')
            || $request->cookie(config('jwt.cookie_key_name', 'token'));
    }

    protected function getDefaultGuard(): string
    {
        return config('auth.defaults.guard', 'web');
    }
}
