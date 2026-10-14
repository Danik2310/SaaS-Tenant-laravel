<?php

namespace App\Shared\Support;

use App\Shared\Middleware\AuthenticateJwtFromCookie;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Factory for the JWT transport cookie.
 *
 * The JWT is carried in an HttpOnly cookie (name from `jwt.cookie_key_name`,
 * TTL from `jwt.refresh_ttl`, `secure` from `session.secure`). `make()` issues it
 * after login; `forget()` clears it on logout. On the next request
 * {@see AuthenticateJwtFromCookie} promotes it back to an
 * `Authorization: Bearer` header.
 */
class JwtCookie
{
    /** Build the HttpOnly JWT cookie for `$token` (TTL = `jwt.refresh_ttl`). */
    public static function make(string $token): SymfonyCookie
    {
        return Cookie::make(
            config('jwt.cookie_key_name', 'token'),
            $token,
            (int) config('jwt.refresh_ttl', 10080),
            '/',
            null,
            (bool) config('session.secure', false),
            true,
            false,
            'lax'
        );
    }

    /** Expire the JWT cookie. */
    public static function forget(): SymfonyCookie
    {
        return Cookie::forget(config('jwt.cookie_key_name', 'token'), '/');
    }
}
