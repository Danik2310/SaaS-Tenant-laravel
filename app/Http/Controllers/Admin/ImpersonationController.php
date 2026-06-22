<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImpersonateTenantRequest;
use App\Models\Tenant;

/**
 * @group Tenant Impersonation
 *
 * APIs for impersonating tenants (god mode).
 */
class ImpersonationController extends Controller
{
    /**
     * Start impersonating a tenant.
     *
     * @authenticated
     *
     * @bodyParam tenant_id string required The tenant ID to impersonate.
     *
     * @response 422 {"message":"Tenant has no domain configured"}
     */
    public function start(ImpersonateTenantRequest $request)
    {
        if (session()->has('impersonate_tenant')) {
            return response()->json(['message' => 'Already impersonating a tenant. Stop current impersonation first.'], 422);
        }

        $tenant = Tenant::with('domains')->find($request->validated('tenant_id'));
        $domain = $tenant->domains->first()?->domain ?? null;

        if (! $domain) {
            return response()->json(['message' => 'Tenant has no domain configured'], 422);
        }

        session([
            'impersonate_tenant' => $tenant->id,
            'impersonate_started_at' => now()->timestamp,
        ]);

        activity('impersonation')
            ->performedOn($tenant)
            ->causedBy(auth('admin')->user())
            ->withProperties(['tenant_name' => $tenant->name, 'domain' => $domain])
            ->log("Impersonated tenant {$tenant->name}");

        return response()->json(['message' => 'Impersonation started', 'domain' => $domain]);
    }

    /**
     * Stop impersonating.
     *
     * @authenticated
     */
    public function stop()
    {
        session()->forget(['impersonate_tenant', 'impersonate_started_at']);

        return response()->json(['message' => 'Impersonation stopped']);
    }
}
