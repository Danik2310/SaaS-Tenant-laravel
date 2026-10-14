<?php

namespace App\Shared\Middleware;

use App\Shared\Support\JwtCookie;
use Closure;
use Illuminate\Http\Request;

/**
 * Alias: `jwt.cookie`.
 *
 * Promotes the JWT held in a cookie into a standard `Authorization: Bearer`
 * header so the rest of the auth stack only ever deals with bearer tokens.
 *
 * Flow:
 *  1. If there is no Authorization header but the JWT cookie is present, copy
 *     the cookie value into `Authorization: Bearer <token>`.
 *  2. Continue (never blocks the request).
 *
 * @see JwtCookie
 */
class AuthenticateJwtFromCookie
{
    public function handle(Request $request, Closure $next)
    {
        $cookieName = config('jwt.cookie_key_name', 'token');

        if (! $request->headers->has('Authorization') && $request->cookie($cookieName)) {
            $request->headers->set('Authorization', 'Bearer '.$request->cookie($cookieName));
        }

        return $next($request);
    }
}
