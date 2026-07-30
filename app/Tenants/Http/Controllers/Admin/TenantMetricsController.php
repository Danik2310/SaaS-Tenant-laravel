<?php

declare(strict_types=1);

namespace App\Tenants\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Http\Resources\TenantResourceUsageResource;
use App\Models\Tenant;
use App\Models\TenantResourceUsage;
use App\Shared\Contracts\ServerDiskInfo;
use Illuminate\Http\Request;

/**
 * @group Tenant Metrics
 *
 * APIs for viewing tenant resource usage metrics.
 */
class TenantMetricsController extends Controller
{
    private ServerDiskInfo $diskInfo;

    public function __construct(ServerDiskInfo $diskInfo)
    {
        $this->diskInfo = $diskInfo;
    }
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

        $perPage = (int) ($validated['per_page'] ?? 5);
        $metrics = $query->orderBy('collected_at', 'desc')->paginate($perPage);

        return response()->json([
            'metrics' => TenantResourceUsageResource::collection($metrics->items()),
            'total' => $metrics->total(),
            'last_page' => $metrics->lastPage(),
        ]);
    }

    /**
     * Aggregate usage summary across all tenants.
     *
     * @authenticated
     *
     * @responseField summary.total_tenants int Number of tenants with usage records.
     * @responseField summary.total_users int Total users across all tenants.
     * @responseField summary.avg_users float Average users per tenant.
     * @responseField summary.max_users int Highest user count in any tenant.
     * @responseField summary.total_storage_mb float Total storage in MB across all tenants.
     * @responseField summary.avg_storage_mb float Average storage per tenant in MB.
     * @responseField summary.max_storage_mb float Highest storage usage in any tenant in MB.
     * @responseField summary.total_products int Total products across all tenants.
     * @responseField summary.avg_products float Average products per tenant.
     * @responseField summary.max_products int Highest product count in any tenant.
     */
    public function summary()
    {
        $stats = TenantResourceUsage::selectRaw('
            COUNT(*) as total_tenants,
            COALESCE(SUM(users_count), 0) as total_users,
            COALESCE(ROUND(AVG(users_count), 1), 0) as avg_users,
            COALESCE(MAX(users_count), 0) as max_users,
            COALESCE(ROUND(SUM(storage_kb) / 1024, 1), 0) as total_storage_mb,
            COALESCE(ROUND(AVG(storage_kb) / 1024, 1), 0) as avg_storage_mb,
            COALESCE(ROUND(MAX(storage_kb) / 1024, 1), 0) as max_storage_mb,
            COALESCE(SUM(products_count), 0) as total_products,
            COALESCE(ROUND(AVG(products_count), 1), 0) as avg_products,
            COALESCE(MAX(products_count), 0) as max_products
        ')->first();

        $stats = (array) $stats;

        $stats['server_disk_total_gb'] = $this->diskInfo->totalGb();
        $stats['server_disk_free_gb'] = $this->diskInfo->freeGb();
        $stats['server_disk_used_gb'] = $this->diskInfo->usedGb();
        $stats['server_disk_pct'] = $this->diskInfo->usedPct();
        $stats['server_disk_driver'] = $this->diskInfo->driver();
        $stats['server_disk_label'] = $this->diskInfo->label();

        return response()->json(['summary' => $stats]);
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
