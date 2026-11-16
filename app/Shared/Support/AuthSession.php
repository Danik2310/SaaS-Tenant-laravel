<?php

namespace App\Shared\Support;

use App\Shared\Middleware\EnsureSessionNotExpired;
use Illuminate\Support\Facades\Session;

/**
 * Login-time anchors for the two server-side session clocks.
 *
 * `auth_started_at` is written once when a JWT is issued at login and is never
 * updated — it enforces the absolute lifetime. `last_activity` is rewritten on
 * every authenticated request and drives the idle timeout. Both live in the
 * Laravel session rather than in the JWT, so they survive token refreshes but
 * end with the session; a missing anchor is treated as expired (fail-closed).
 *
 * @see EnsureSessionNotExpired
 * @see JwtCookie
 */
class AuthSession
{
    /** Login timestamp (unix seconds); never moves. */
    public const STARTED_AT = 'auth_started_at';

    /** Last authenticated request (unix seconds); slides on activity. */
    public const LAST_ACTIVITY = 'last_activity';

    /**
     * Anchor a freshly authenticated session.
     */
    public static function begin(): void
    {
        $now = now()->timestamp;

        Session::put([
            self::STARTED_AT => $now,
            self::LAST_ACTIVITY => $now,
        ]);
    }

    /**
     * Record activity for the idle clock.
     */
    public static function touch(): void
    {
        Session::put(self::LAST_ACTIVITY, now()->timestamp);
    }

    /**
     * Idle limit in minutes; 0 disables the idle check.
     */
    public static function idleLifetime(): int
    {
        return max(0, (int) config('session.idle_lifetime', 30));
    }

    /**
     * Absolute limit in minutes; 0 disables the absolute check.
     */
    public static function absoluteLifetime(): int
    {
        return max(0, (int) config('session.absolute_lifetime', 720));
    }

    /**
     * Whether the idle or absolute limit has passed.
     *
     * Missing anchors count as expired: a bearer token without a login anchor
     * means the session that proved the login is gone (expired session cookie,
     * or a session created before the anchors existed), so a still-refreshable
     * JWT must not silently re-establish it.
     */
    public static function expired(): bool
    {
        $lastActivity = Session::get(self::LAST_ACTIVITY);
        $startedAt = Session::get(self::STARTED_AT);

        if (! is_numeric($lastActivity) || ! is_numeric($startedAt)) {
            return true;
        }

        $now = now()->timestamp;
        $idle = self::idleLifetime();
        $absolute = self::absoluteLifetime();

        if ($idle > 0 && ($now - (int) $lastActivity) > $idle * 60) {
            return true;
        }

        if ($absolute > 0 && ($now - (int) $startedAt) > $absolute * 60) {
            return true;
        }

        return false;
    }
}
