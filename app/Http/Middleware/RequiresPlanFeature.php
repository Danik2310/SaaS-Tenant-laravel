<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresPlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = tenant();

        if (!$tenant || !$tenant instanceof \App\Models\Tenant || !$tenant->hasFeature($feature)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => "Your current plan does not include {$feature}.",
                ], 403);
            }

            return abort(403, 'Upgrade your plan to access this feature.');
        }

        return $next($request);
    }
}
