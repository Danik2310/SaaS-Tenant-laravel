<?php

namespace App\Http\Controllers;

use App\Contracts\TenantManagerInterface;
use App\Http\Requests\Admin\BulkTenantOperationRequest;
use App\Http\Requests\Admin\ImpersonateTenantRequest;
use App\Http\Requests\Admin\StoreTenantRequest;
use App\Http\Requests\Admin\UpdateTenantRequest;
use App\Http\Resources\PlanResource;
use App\Http\Resources\TenantResource;
use App\Models\AdminUser;
use App\Models\Plan;
use App\Models\Tenant;
use App\States\TenantStateManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class AdminDashboardController extends Controller
{
    public function __construct(
        private TenantManagerInterface $tenantManager,
    ) {}

    public function dashboardStats()
    {
        $totalTenants = Tenant::withTrashed()->count();
        $activeTenants = Tenant::where('status', 'Active')->count();
        $suspendedTenants = Tenant::where('status', 'Suspended')->count();
        $deletedTenants = Tenant::onlyTrashed()->count();

        $staffCount = AdminUser::count();
        $activeStaff = AdminUser::where('is_active', true)->count();

        $plansCount = Plan::count();

        $tenantQuery = request()->boolean('trashed')
            ? Tenant::withTrashed()
            : Tenant::query();

        $recentTenants = $tenantQuery->clone()
            ->with('domains')
            ->latest()
            ->take(7)
            ->get()
            ->map(function ($tenant) {
                return [
                    'name' => $tenant->name,
                    'domain' => $tenant->domains->first()?->domain ?? 'N/A',
                    'status' => $tenant->status,
                    'created_at' => $tenant->created_at->format('Y-m-d'),
                ];
            })->values()->toArray();

        $tenantsByMonth = $tenantQuery->clone()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->toArray();

        $statusDistribution = [
            ['name' => 'Active', 'value' => $activeTenants],
            ['name' => 'Suspended', 'value' => $suspendedTenants],
            ['name' => 'Deleted', 'value' => $deletedTenants],
        ];

        return response()->json([
            'stats' => [
                'total_tenants' => $totalTenants,
                'active_tenants' => $activeTenants,
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

    public function index()
    {
        return view('admin.dashboard');
    }

    public function tenants()
    {
        $query = Tenant::with(['domains', 'plan']);

        if (request()->boolean('trashed')) {
            $query->withTrashed();
        }

        $perPage = min((int) request('per_page', 25), 100);
        $tenants = $query->paginate($perPage);

        return response()->json([
            'tenants' => TenantResource::collection($tenants->items()),
            'total' => $tenants->total(),
            'meta' => [
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
                'per_page' => $tenants->perPage(),
                'total' => $tenants->total(),
            ],
        ]);
    }

    public function showTenant(string $id)
    {
        $tenant = Tenant::withTrashed()->with(['domains', 'plan'])->findOrFail($id);

        return response()->json([
            'tenant' => new TenantResource($tenant),
        ]);
    }

    public function createTenant(StoreTenantRequest $request)
    {
        $tenant = $this->tenantManager->provision($request->validated());

        return response()->json([
            'message' => 'Tenant created successfully',
            'tenant' => new TenantResource($tenant),
        ], 201);
    }

    public function updateTenant(string $id, UpdateTenantRequest $request)
    {
        $tenant = Tenant::findOrFail($id);

        $data = $request->validated();
        $status = $data['status'] ?? null;
        unset($data['status']);

        foreach ($data as $key => $value) {
            if ($value !== null) {
                $tenant->$key = $value;
            }
        }

        if ($status !== null) {
            try {
                TenantStateManager::transitionTo($tenant, $status);
            } catch (InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        } else {
            $tenant->save();
        }

        $tenant->refresh();

        return response()->json([
            'message' => 'Tenant updated successfully',
            'tenant' => new TenantResource($tenant),
        ]);
    }

    public function deleteTenant(string $id)
    {
        $tenant = Tenant::findOrFail($id);

        $this->tenantManager->delete($tenant);

        return response()->noContent();
    }

    public function restoreTenant(string $id)
    {
        $tenant = Tenant::withTrashed()->findOrFail($id);

        $this->tenantManager->restore($tenant);

        return response()->json(['message' => 'Tenant restored successfully', 'tenant' => new TenantResource($tenant)]);
    }

    public function tenantDatabase(string $id)
    {
        $tenant = Tenant::withTrashed()->findOrFail($id);
        $db = $tenant->database();

        return response()->json([
            'database' => [
                'name' => $db->getName(),
                'connection' => $db->connection()['driver'] ?? 'mysql',
            ],
        ]);
    }

    public function migrateTenant(string $id)
    {
        try {
            $tenant = Tenant::findOrFail($id);

            $exitCode = \Artisan::call('tenants:migrate', [
                '--tenants' => [$tenant->id],
            ]);

            $output = \Artisan::output();

            activity('tenant')
                ->performedOn($tenant)
                ->causedBy(auth('admin')->user())
                ->withProperties(['tenant_name' => $tenant->name])
                ->log("Ran migrations for tenant {$tenant->name}");

            return response()->json([
                'message' => $exitCode === 0 ? 'Migrations executed successfully' : 'Migrations completed with warnings',
                'output' => $output,
                'exit' => $exitCode,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Migration failed',
                'error' => $e->getMessage(),
                'output' => isset($output) ? $output : '',
            ], 500);
        }
    }

    public function bulkTenantOperation(BulkTenantOperationRequest $request)
    {
        $validated = $request->validated();
        $action = $validated['action'];
        $payload = $validated['payload'] ?? [];
        $tenantIds = $validated['tenant_ids'];
        $adminUser = auth('admin')->user();

        $tenants = Tenant::withTrashed()->whereIn('id', $tenantIds)->get()->keyBy('id');

        if ($action === 'change_plan') {
            $newPlan = Plan::findOrFail($payload['plan_id']);
        }

        $results = [];
        $succeeded = 0;
        $failed = 0;

        foreach ($tenantIds as $id) {
            try {
                $tenant = $tenants->get($id);

                if (! $tenant) {
                    throw new \RuntimeException("Tenant not found");
                }

                match ($action) {
                    'suspend' => $this->tenantManager->suspend($tenant),
                    'activate' => $tenant->trashed()
                        ? $this->tenantManager->restore($tenant)
                        : TenantStateManager::transitionTo($tenant, 'Active'),
                    'delete' => $this->tenantManager->delete($tenant),
                    'restore' => $this->tenantManager->restore($tenant),
                    'change_plan' => $this->tenantManager->changePlan($tenant, $newPlan),
                    'extend_trial' => $this->extendTenantTrial($tenant, $payload['days']),
                };

                activity($action === 'delete' ? 'tenant' : 'tenant')
                    ->performedOn($tenant)
                    ->causedBy($adminUser)
                    ->withProperties(['tenant_name' => $tenant->name, 'action' => $action, 'payload' => $payload])
                    ->log("Bulk {$action} for tenant {$tenant->name}");

                $results[] = ['tenant_id' => $id, 'status' => 'success'];
                $succeeded++;
            } catch (\Exception $e) {
                \Log::warning("Bulk operation failed for tenant {$id}: {$e->getMessage()}");
                $results[] = ['tenant_id' => $id, 'status' => 'failed', 'error' => 'An error occurred while processing this tenant.'];
                $failed++;
            }
        }

        return response()->json([
            'total' => count($tenantIds),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'results' => $results,
        ]);
    }

    private function extendTenantTrial(Tenant $tenant, int $days): void
    {
        $currentEnd = $tenant->trial_ends_at ? $tenant->trial_ends_at->copy() : now();
        $tenant->trial_ends_at = $currentEnd->addDays($days);
        $tenant->save();
    }

    public function staff()
    {
        return response()->json(['staff' => []]);
    }

    public function plans()
    {
        $plans = Cache::remember('admin_plans_list', 3600, fn () => Plan::all());

        return response()->json([
            'plans' => PlanResource::collection($plans),
        ]);
    }

    public function impersonateTenant(ImpersonateTenantRequest $request)
    {
        $tenant = Tenant::with('domains')->find($request->validated('tenant_id'));
        $domain = $tenant->domains->first()?->domain ?? null;

        if (! $domain) {
            return response()->json(['message' => 'Tenant has no domain configured'], 422);
        }

        session(['impersonate_tenant' => $tenant->id]);

        activity('impersonation')
            ->performedOn($tenant)
            ->causedBy(auth('admin')->user())
            ->withProperties(['tenant_name' => $tenant->name, 'domain' => $domain])
            ->log("Impersonated tenant {$tenant->name}");

        return response()->json(['message' => 'Impersonation started', 'domain' => $domain]);
    }

    public function stopImpersonation()
    {
        session()->forget('impersonate_tenant');

        return response()->json(['message' => 'Impersonation stopped']);
    }

    public function changeTenantPlan(Request $request, string $id)
    {
        $request->validate(['plan_id' => 'required|integer|exists:plans,id']);

        $tenant = Tenant::with('plan')->findOrFail($id);
        $newPlan = Plan::findOrFail($request->input('plan_id'));
        $oldPlanName = $tenant->plan?->name ?? 'None';

        $this->tenantManager->changePlan($tenant, $newPlan);

        $tenant->refresh();

        activity('tenant')
            ->performedOn($tenant)
            ->causedBy(auth('admin')->user())
            ->withProperties([
                'tenant_name' => $tenant->name,
                'from_plan' => $oldPlanName,
                'to_plan' => $newPlan->name,
            ])
            ->log("Changed plan for {$tenant->name}: {$oldPlanName} → {$newPlan->name}");

        return response()->json([
            'message' => 'Tenant plan changed successfully',
            'tenant' => new TenantResource($tenant),
        ]);
    }
}
