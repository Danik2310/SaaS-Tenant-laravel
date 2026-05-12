<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResourceUsageResource;
use App\Models\Tenant;
use App\Models\TenantResourceUsage;
use Illuminate\Http\Request;

class TenantMetricsController extends Controller
{
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

        $perPage = (int) $request->input('per_page', 25);
        $metrics = $query->orderBy('collected_at', 'desc')->paginate($perPage);

        return response()->json([
            'metrics' => TenantResourceUsageResource::collection($metrics->items()),
            'total' => $metrics->total(),
            'meta' => [
                'current_page' => $metrics->currentPage(),
                'last_page' => $metrics->lastPage(),
                'per_page' => $metrics->perPage(),
                'total' => $metrics->total(),
            ],
        ]);
    }

    public function show(string $tenantId)
    {
        $tenant = Tenant::withTrashed()->findOrFail($tenantId);
        $usage = TenantResourceUsage::where('tenant_id', $tenantId)->first();

        $plan = $tenant->plan;

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'email' => $tenant->email,
                'status' => $tenant->status,
                'plan_name' => $plan?->name ?? 'No Plan',
            ],
            'usage' => $usage ? [
                'users_count' => $usage->users_count,
                'products_count' => $usage->products_count,
                'orders_count' => $usage->orders_count,
                'storage_kb' => $usage->storage_kb,
                'db_size_kb' => $usage->db_size_kb,
                'collected_at' => $usage->collected_at,
            ] : null,
            'limits' => [
                'max_users' => $plan?->max_users,
                'max_storage' => $plan?->max_storage,
            ],
        ]);
    }
}
