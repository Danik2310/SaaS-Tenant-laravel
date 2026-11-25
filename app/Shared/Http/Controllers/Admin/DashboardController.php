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
 *
 * Flow: `index()` renders the SPA shell; `stats()` aggregates tenant/staff/plan
 * counts and trends behind a 5-minute cache (bypassed with `?refresh=1`).
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

        $snapshot = Cache::remember($cacheKey, 300, function () {
            $comparisonDate = now()->subDays(30);

            $totalTenants = Tenant::withTrashed()->count();
            $activeTenants = Tenant::where('status', 'Active')->count();
            $trialTenants = Tenant::where('status', 'Trial')->count();
            $suspendedTenants = Tenant::where('status', 'Suspended')->count();
            $deletedTenants = Tenant::onlyTrashed()->count();

            $staffCount = AdminUser::count();
            $activeStaff = AdminUser::where('is_active', true)->count();

            $plansCount = Plan::count();

            $existingTenantsAt = function () use ($comparisonDate) {
                return Tenant::withTrashed()
                    ->where('created_at', '<=', $comparisonDate)
                    ->where(function ($query) use ($comparisonDate) {
                        $query->whereNull('deleted_at')
                            ->orWhere('deleted_at', '>', $comparisonDate);
                    });
            };

            $totalTenantsAtDate = $existingTenantsAt()->count();
            $deletedTenantsAtDate = Tenant::onlyTrashed()
                ->where('created_at', '<=', $comparisonDate)
                ->where('deleted_at', '<=', $comparisonDate)
                ->count();

            $statusAtDate = function (string $status) use ($existingTenantsAt) {
                return $existingTenantsAt()->where('status', $status)->count();
            };

            $staffAtDate = AdminUser::where('created_at', '<=', $comparisonDate)->count();
            $plansAtDate = Plan::where('created_at', '<=', $comparisonDate)->count();

            $makeTrend = function (int $current, int $previous, string $label = 'vs last 30 days'): array {
                if ($previous === 0) {
                    return [
                        'direction' => $current > 0 ? 'up' : 'flat',
                        'percentage' => null,
                        'label' => $current > 0 ? 'new in last 30 days' : $label,
                    ];
                }

                $change = (($current - $previous) / $previous) * 100;

                return [
                    'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
                    'percentage' => (int) round(abs($change)),
                    'label' => $label,
                ];
            };

            $trends = [
                'total_tenants' => $makeTrend($totalTenants, $totalTenantsAtDate),
                'active_tenants' => $makeTrend($activeTenants, $statusAtDate('Active'), 'status mix vs 30d ago'),
                'suspended_tenants' => $makeTrend($suspendedTenants, $statusAtDate('Suspended'), 'status mix vs 30d ago'),
                'deleted_tenants' => $makeTrend($deletedTenants, $deletedTenantsAtDate),
                'total_staff' => $makeTrend($staffCount, $staffAtDate),
                'total_plans' => $makeTrend($plansCount, $plansAtDate),
            ];

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

            return [
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
                'trends' => $trends,
                'recent_tenants' => $recentTenants,
                'tenants_by_month' => $tenantsByMonth,
            ];
        });

        $stats = $snapshot['stats'];

        $statusDistribution = [
            ['name' => 'Active', 'value' => $stats['active_tenants']],
            ['name' => 'Trial', 'value' => $stats['trial_tenants']],
            ['name' => 'Suspended', 'value' => $stats['suspended_tenants']],
            ['name' => 'Deleted', 'value' => $stats['deleted_tenants']],
        ];

        return response()->json([
            'stats' => $stats,
            'trends' => $snapshot['trends'],
            'recent_tenants' => $snapshot['recent_tenants'],
            'tenants_by_month' => $snapshot['tenants_by_month'],
            'status_distribution' => $statusDistribution,
        ]);
    }
}
