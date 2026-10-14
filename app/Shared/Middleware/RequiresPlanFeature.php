<?php

declare(strict_types=1);

namespace App\Shared\Middleware;

use App\Models\Tenant;
use App\Plans\Support\FeatureFlagCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias: `feature` (e.g. `feature:advanced`).
 *
 * Blocks a route when the tenant's plan does not include the named feature.
 *
 * Flow:
 *  1. If there is no tenant, or the tenant lacks the feature, return a 403
 *     (JSON) or redirect to `billing.upgrade` with an error.
 *  2. Otherwise continue.
 *
 * @see FeatureFlagCatalog
 */
class RequiresPlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = tenant();

        if (! $tenant || ! $tenant instanceof Tenant || ! $tenant->hasFeature($feature)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => "Your current plan does not include {$feature}.",
                ], 403);
            }

            return redirect()->route('billing.upgrade')
                ->with('error', 'Your current plan does not include '.$feature.'. Please upgrade to access this feature.');
        }

        return $next($request);
    }
}
