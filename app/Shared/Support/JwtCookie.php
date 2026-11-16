<?php

namespace App\Shared\Support;

use App\Shared\Middleware\AuthenticateJwtFromCookie;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Factory for the JWT transport cookie.
 *
 * The JWT is carried in an HttpOnly cookie (name from `jwt.cookie_key_name`,
 * TTL from `jwt.refresh_ttl`, capped at `session.absolute_lifetime`, and
 * `secure` from `session.secure`). `make()` issues it after login; `forget()`
 * clears it on logout. On the next request {@see AuthenticateJwtFromCookie}
 * promotes it back to an `Authorization: Bearer` header.
 */
class JwtCookie
{
    /** Build the HttpOnly JWT cookie for `$token`. */
    public static function make(string $token): SymfonyCookie
    {
        return Cookie::make(
            config('jwt.cookie_key_name', 'token'),
            $token,
            self::lifetime(),
            '/',
            null,
            (bool) config('session.secure', false),
            true,
            false,
            'lax'
        );
    }

    /**
     * Transport lifetime in minutes: `jwt.refresh_ttl`, capped so the cookie
     * can never outlive the absolute session lifetime it belongs to.
     */
    protected static function lifetime(): int
    {
        $refreshTtl = (int) config('jwt.refresh_ttl', 10080);
        $absoluteLifetime = (int) config('session.absolute_lifetime', 720);

        if ($absoluteLifetime > 0 && $refreshTtl > $absoluteLifetime) {
            return $absoluteLifetime;
        }

        return $refreshTtl;
    }

    /** Expire the JWT cookie. */
    public static function forget(): SymfonyCookie
    {
        return Cookie::forget(config('jwt.cookie_key_name', 'token'), '/');
    }
}
