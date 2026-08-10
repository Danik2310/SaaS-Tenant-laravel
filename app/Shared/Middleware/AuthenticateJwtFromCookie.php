<?php

namespace App\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;

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
