<?php

namespace App\Shared\Middleware;

use Closure;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

/**
 * Alias: `jwt.tenant`.
 *
 * Ensures the JWT was minted for the tenant currently being served, so a token
 * issued for one tenant cannot be replayed against another.
 *
 * Flow:
 *  1. Impersonation sessions carry no tenant JWT → skip the check.
 *  2. Read the token's `ten` (tenant) claim; a JWT error → 401.
 *  3. The claim must equal the current tenant id, otherwise 401.
 */
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
