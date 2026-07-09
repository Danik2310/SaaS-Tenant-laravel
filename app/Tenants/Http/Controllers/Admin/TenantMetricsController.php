<?php

declare(strict_types=1);

namespace App\Tenants\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Http\Resources\TenantResourceUsageResource;
use App\Models\Tenant;
use App\Models\TenantResourceUsage;
use Illuminate\Http\Request;

/**
 * @group Tenant Metrics
 *
 * APIs for viewing tenant resource usage metrics.
 */
class TenantMetricsController extends Controller
{
    /**
     * List tenant metrics.
     *
     * Paginated list of tenant resource usage data.
     *
     * @authenticated
     *
     * @queryParam per_page integer Results per page (max 100). Example: 25
     * @queryParam exclude_empty boolean Exclude tenants with zero usage. Example: true
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'exclude_empty' => ['sometimes', 'boolean'],
        ]);

        $query = TenantResourceUsage::with('tenant');

        if ($request->boolean('exclude_empty')) {
            $query->where(function ($q) {
                $q->where('users_count', '>', 0)
                    ->orWhere('products_count', '>', 0)
                    ->orWhere('orders_count', '>', 0)
                    ->orWhere('storage_kb', '>', 0)
                    ->orWhere('db_size_kb', '>', 0);
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 25);
        $metrics = $query->orderBy('collected_at', 'desc')->paginate($perPage);

        return response()->json([
            'metrics' => TenantResourceUsageResource::collection($metrics->items()),
            'meta' => [
                'current_page' => $metrics->currentPage(),
                'last_page' => $metrics->lastPage(),
                'per_page' => $metrics->perPage(),
                'total' => $metrics->total(),
            ],
        ]);
    }

    /**
     * Get metrics for a single tenant.
     *
     * @authenticated
     *
     * @urlParam tenantId string required The tenant ID.
     */
    public function show(string $tenantId)
    {
        $tenant = Tenant::withTrashed()->with('plan')->findOrFail($tenantId);
        $usage = TenantResourceUsage::where('tenant_id', $tenantId)->first();

        return response()->json([
            'metrics' => [
                'tenant' => new TenantResource($tenant),
                'usage' => $usage ? new TenantResourceUsageResource($usage) : null,
                'limits' => [
                    'max_users' => $tenant->plan?->max_users,
                    'max_storage' => $tenant->plan?->max_storage,
                ],
            ],
        ]);
    }
}
