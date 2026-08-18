<?php

namespace App\Shared\Middleware;

use App\Shared\Support\JwtCookie;
use Closure;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;

class RefreshJwtToken
{
    public function __construct(protected AuthManager $auth) {}

    public function handle(Request $request, Closure $next, ?string $guard = null)
    {
        $guardName = $guard ?: $this->auth->getDefaultDriver();

        if (! $request->headers->has('Authorization')) {
            return $next($request);
        }

        $guardInstance = $this->auth->guard($guardName);

        try {
            $payload = $guardInstance->getPayload();
        } catch (TokenExpiredException $e) {
            return $this->refreshToken($request, $next, $guardInstance);
        } catch (JWTException $e) {
            return $next($request);
        }

        $ttlSeconds = (int) config('jwt.ttl') * 60;
        $remainingSeconds = $payload->get('exp') - time();

        if ($remainingSeconds <= $ttlSeconds / 2) {
            return $this->refreshToken($request, $next, $guardInstance);
        }

        return $next($request);
    }

    protected function refreshToken($request, $next, $guardInstance)
    {
        try {
            $token = $guardInstance->refresh();
            $guardInstance->setToken($token);

            return $next($request)->withCookie(JwtCookie::make($token));
        } catch (JWTException $e) {
            return $next($request);
        }
    }
}
