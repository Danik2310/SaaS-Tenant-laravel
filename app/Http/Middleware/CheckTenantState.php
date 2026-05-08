<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantState
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant && $tenant instanceof \App\Models\Tenant && $tenant->status !== 'Active') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your account has been suspended. Please contact support.',
                ], 403);
            }

            return redirect()->route('tenant.suspended');
        }

        return $next($request);
    }
}
