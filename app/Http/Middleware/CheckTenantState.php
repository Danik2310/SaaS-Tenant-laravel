<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantState
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant && $tenant instanceof Tenant && !in_array($tenant->status, ['Active', 'Trial'])) {
            $isDeleted = $tenant->status === 'Deleted';

            if ($request->expectsJson()) {
                $message = $isDeleted
                    ? 'This account has been deleted. Please contact support.'
                    : 'Your account has been suspended. Please contact support.';

                return response()->json(['message' => $message], 403);
            }

            return response()->view('admin.tenant-state', [
                'status' => $tenant->status,
                'isDeleted' => $isDeleted,
                'tenantName' => $tenant->name,
            ]);
        }

        return $next($request);
    }
}
