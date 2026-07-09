<?php

namespace App\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;

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
