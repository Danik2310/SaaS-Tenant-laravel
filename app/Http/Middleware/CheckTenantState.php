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

        if ($tenant && $tenant instanceof Tenant) {
            if ($tenant->trashed() || $tenant->status === 'Deleted') {
                $message = 'This account has been deleted. Please contact support.';

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $message,
                    ], 403);
                }

                return response()->view('admin.tenant-state', [
                    'status' => 'Deleted',
                    'isDeleted' => true,
                    'tenantName' => $tenant->name,
                ]);
            }

            if ($tenant->status === 'Trial' && $tenant->trialHasExpired()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Your trial has expired. Please upgrade to continue.',
                    ], 403);
                }

                return response()->view('admin.tenant-state', [
                    'status' => 'Trial',
                    'isDeleted' => false,
                    'tenantName' => $tenant->name,
                ]);
            }

            if (! in_array($tenant->status, ['Active', 'Trial'])) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Your account has been suspended. Please contact support.',
                    ], 403);
                }

                return response()->view('admin.tenant-state', [
                    'status' => $tenant->status,
                    'isDeleted' => false,
                    'tenantName' => $tenant->name,
                ]);
            }
        }

        return $next($request);
    }
}
