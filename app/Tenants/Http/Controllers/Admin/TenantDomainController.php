<?php

declare(strict_types=1);

namespace App\Tenants\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTenantDomainRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;

/**
 * @group Tenant Management
 *
 * APIs for managing the domains attached to a tenant.
 */
class TenantDomainController extends Controller
{
    /**
     * Attach a domain to a tenant.
     *
     * Adds a secondary domain. The first-attached domain remains the primary
     * domain; the tenant's `domain` column and existing `is_primary` rows are
     * never modified.
     *
     * @authenticated
     *
     * @urlParam id string required The tenant ID. Example: tenant-abc-123
     *
     * @bodyParam domain string required The domain to attach. Example: acme-backup.sasapp
     *
     * @responseField message string Success message.
     * @responseField tenant object The updated tenant resource.
     *
     * @throws 422 If the domain is already in use by another tenant.
     */
    public function store(StoreTenantDomainRequest $request, string $id)
    {
        $tenant = Tenant::findOrFail($id);

        $tenant->domains()->create([
            'domain' => $request->validated('domain'),
            'is_primary' => false,
        ]);

        return response()->json([
            'message' => 'Domain added successfully',
            'tenant' => new TenantResource($tenant->fresh(['domains'])),
        ], 201);
    }

    /**
     * Detach a domain from a tenant.
     *
     * The primary domain (or the last remaining domain) cannot be removed, so
     * the tenant always retains at least one routable domain.
     *
     * @authenticated
     *
     * @urlParam id string required The tenant ID. Example: tenant-abc-123
     * @urlParam domainId int required The domain row ID. Example: 5
     *
     * @responseField message string Success message.
     * @responseField tenant object The updated tenant resource.
     *
     * @throws 422 If the domain is the primary or last remaining domain.
     */
    public function destroy(string $id, string $domainId)
    {
        $tenant = Tenant::findOrFail($id);
        $domain = $tenant->domains()->findOrFail($domainId);

        $isPrimary = (bool) $domain->is_primary;
        $isLast = $tenant->domains()->count() <= 1;

        if ($isPrimary || $isLast) {
            return response()->json([
                'message' => 'The primary domain cannot be removed. Add another domain before detaching it.',
            ], 422);
        }

        $domain->delete();

        return response()->json([
            'message' => 'Domain removed successfully',
            'tenant' => new TenantResource($tenant->fresh(['domains'])),
        ]);
    }
}
