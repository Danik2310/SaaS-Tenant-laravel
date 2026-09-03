<?php

namespace App\Shared\Middleware;

use Closure;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class ValidateTenantToken
{
    public function __construct(protected AuthManager $auth) {}

    public function handle(Request $request, Closure $next, ?string $guard = null)
    {
        // Impersonation (god mode) sessions carry no tenant JWT; they are
        // already bound to the correct tenant by the entry token and the
        // EnsureImpersonationValid middleware, so skip token checks.
        if ($request->session()->has('impersonation')) {
            return $next($request);
        }

        $guardName = $guard ?: $this->auth->getDefaultDriver();

        try {
            $tokenTenant = $this->auth->guard($guardName)->getPayload()->get('ten');
        } catch (JWTException $e) {
            abort(401);
        }

        if ((string) $tokenTenant !== (string) tenant('id')) {
            abort(401);
        }

        return $next($request);
    }
}
