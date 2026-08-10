<?php

namespace App\Shared\Support;

use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class JwtCookie
{
    public static function make(string $token): SymfonyCookie
    {
        return Cookie::make(
            config('jwt.cookie_key_name', 'token'),
            $token,
            (int) config('jwt.refresh_ttl', 10080),
            '/',
            null,
            (bool) env('SESSION_SECURE_COOKIE', false),
            true,
            false,
            'lax'
        );
    }

    public static function forget(): SymfonyCookie
    {
        return Cookie::forget(config('jwt.cookie_key_name', 'token'), '/');
    }
}
