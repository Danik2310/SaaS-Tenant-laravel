<?php

namespace App\Shared\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * @group Admin Dashboard
 *
 * APIs for admin dashboard statistics and overview.
 */
class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard');
    }

    /**
     * Get dashboard statistics.
     *
     * Cached for 5 minutes. Includes tenant counts, staff counts, recent tenants, and trends.
     *
     * @authenticated
     *
     * @queryParam trashed boolean Include trashed tenants in statistics. Example: true
     */
    public function stats()
    {
        $cacheKey = 'admin_dashboard_stats_'.(request()->boolean('trashed') ? 'trashed' : 'active');

        if (request()->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        [$totalTenants, $activeTenants, $trialTenants, $suspendedTenants, $deletedTenants, $staffCount, $activeStaff, $plansCount, $recentTenants, $tenantsByMonth] = Cache::remember($cacheKey, 300, function () {
            $totalTenants = Tenant::withTrashed()->count();
            $activeTenants = Tenant::where('status', 'Active')->count();
            $trialTenants = Tenant::where('status', 'Trial')->count();
            $suspendedTenants = Tenant::where('status', 'Suspended')->count();
            $deletedTenants = Tenant::onlyTrashed()->count();

            $staffCount = AdminUser::count();
            $activeStaff = AdminUser::where('is_active', true)->count();

            $plansCount = Plan::count();

            $tenantQuery = request()->boolean('trashed')
                ? Tenant::withTrashed()
                : Tenant::query();

            $recentTenants = $tenantQuery->clone()
                ->select(['id', 'name', 'status', 'trial_ends_at', 'created_at'])
                ->with('domains:tenant_id,domain')
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($tenant) {
                    return [
                        'name' => $tenant->name,
                        'domain' => $tenant->domains->first()?->domain ?? 'N/A',
                        'status' => $tenant->status,
                        'trial_ends_at' => $tenant->trial_ends_at,
                        'created_at' => $tenant->created_at->format('Y-m-d'),
                    ];
                })->values()->toArray();

            $tenantsByMonth = $tenantQuery->clone()
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->toArray();

            return [$totalTenants, $activeTenants, $trialTenants, $suspendedTenants, $deletedTenants, $staffCount, $activeStaff, $plansCount, $recentTenants, $tenantsByMonth];
        });

        $statusDistribution = [
            ['name' => 'Active', 'value' => $activeTenants],
            ['name' => 'Trial', 'value' => $trialTenants],
            ['name' => 'Suspended', 'value' => $suspendedTenants],
            ['name' => 'Deleted', 'value' => $deletedTenants],
        ];

        return response()->json([
            'stats' => [
                'total_tenants' => $totalTenants,
                'active_tenants' => $activeTenants,
                'trial_tenants' => $trialTenants,
                'suspended_tenants' => $suspendedTenants,
                'deleted_tenants' => $deletedTenants,
                'total_staff' => $staffCount,
                'active_staff' => $activeStaff,
                'total_plans' => $plansCount,
            ],
            'recent_tenants' => $recentTenants,
            'tenants_by_month' => $tenantsByMonth,
            'status_distribution' => $statusDistribution,
        ]);
    }
}
