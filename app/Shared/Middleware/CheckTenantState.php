<?php

declare(strict_types=1);

namespace App\Shared\Middleware;

use App\Models\Tenant;
use App\Tenants\States\ActiveState;
use App\Tenants\States\DeletedState;
use App\Tenants\States\TrialState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantState
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant && $tenant instanceof Tenant) {
            if ($tenant->trashed() || $tenant->status === DeletedState::label()) {
                $message = 'This account has been deleted. Please contact support.';

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $message,
                    ], 403);
                }

                return response()->view('admin.tenant-state', [
                    'status' => DeletedState::label(),
                    'isDeleted' => true,
                    'tenantName' => $tenant->name,
                ]);
            }

            if ($tenant->status === TrialState::label() && $tenant->trialHasExpired()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Your trial has expired. Please upgrade to continue.',
                    ], 403);
                }

                return response()->view('admin.tenant-state', [
                    'status' => TrialState::label(),
                    'isDeleted' => false,
                    'tenantName' => $tenant->name,
                ]);
            }

            if (! in_array($tenant->status, [ActiveState::label(), TrialState::label()])) {
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
