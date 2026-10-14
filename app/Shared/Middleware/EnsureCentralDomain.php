<?php

namespace App\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Alias: `central.domain`.
 *
 * Central-domain guard: central-only routes must not be reachable from a tenant
 * host, so any non-central host is treated as not found.
 *
 * Flow:
 *  1. If the request host is not in `tenancy.central_domains`, abort 404.
 *  2. Otherwise continue.
 */
class EnsureCentralDomain
{
    public function handle(Request $request, Closure $next)
    {
        if (! in_array($request->getHost(), config('tenancy.central_domains'))) {
            abort(404);
        }

        return $next($request);
    }
}
