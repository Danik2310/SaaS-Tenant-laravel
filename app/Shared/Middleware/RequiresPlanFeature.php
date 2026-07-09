<?php

declare(strict_types=1);

namespace App\Shared\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
