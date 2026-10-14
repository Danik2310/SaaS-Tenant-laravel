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

/**
 * Alias: `tenant.state`.
 *
 * Gates the whole tenant route group on the tenant's lifecycle state.
 *
 * Flow:
 *  1. Deleted / trashed → 403 JSON, or the tenant-state page (the upgrade route
 *     redirects to login instead).
 *  2. Trial that has expired → allow only the upgrade route; otherwise redirect
 *     to upgrade (or 403 JSON).
 *  3. Any state other than Active/Trial → suspended response (upgrade route
 *     exempt).
 *  4. Active/Trial → continue.
 */
class CheckTenantState
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant && $tenant instanceof Tenant) {
            $isUpgradeRoute = $request->routeIs('billing.upgrade');

            if ($tenant->trashed() || $tenant->status === DeletedState::label()) {
                if ($isUpgradeRoute) {
                    return redirect()->route('login')
                        ->with('error', 'This account has been deleted. Please contact support.');
                }

                $message = 'This account has been deleted. Please contact support.';

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 403);
                }

                return response()->view('admin.tenant-state', [
                    'status' => DeletedState::label(),
                    'isDeleted' => true,
                    'tenantName' => $tenant->name,
                ]);
            }

            if ($tenant->status === TrialState::label() && $tenant->trialHasExpired()) {
                if ($isUpgradeRoute) {
                    return $next($request);
                }

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Your trial has expired. Please upgrade to continue.',
                        'upgrade_url' => route('billing.upgrade'),
                    ], 403);
                }

                return redirect()->route('billing.upgrade')
                    ->with('error', 'Your trial has expired. Please upgrade to continue.');
            }

            if (! in_array($tenant->status, [ActiveState::label(), TrialState::label()])) {
                if ($isUpgradeRoute) {
                    return $next($request);
                }

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
