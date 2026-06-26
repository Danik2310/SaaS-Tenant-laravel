<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\TenantManagerInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkTenantOperationRequest;
use App\Http\Requests\Admin\ChangeTenantPlanRequest;
use App\Http\Requests\Admin\StoreTenantRequest;
use App\Http\Requests\Admin\UpdateTenantRequest;
use App\Http\Resources\PlanResource;
use App\Http\Resources\TenantResource;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * @group Tenant Management
 *
 * APIs for managing tenants in the admin panel.
 * Includes CRUD, bulk operations, state transitions, plan changes, and database management.
 */
class TenantController extends Controller
{
    public function __construct(
        private TenantManagerInterface $tenantManager,
    ) {}

    /**
     * List all tenants.
     *
     * Paginated list of tenants with optional trashed filtering.
     *
     * @authenticated
     *
     * @queryParam trashed boolean Include soft-deleted tenants. Default false. Example: true
     * @queryParam per_page integer Number of results per page (max 100). Default 25. Example: 10
     *
     * @responseField tenants array[] List of tenant resources.
     * @responseField total integer Total number of tenants matching the query.
     * @responseField meta object Pagination metadata (current_page, last_page, per_page, total).
     */
    public function index()
    {
        $query = Tenant::with(['domains', 'plan.featureGates']);

        if (request()->boolean('trashed')) {
            $query->withTrashed();
        }

        $perPage = min((int) request('per_page', 25), 100);
        $tenants = $query->paginate($perPage);

        return response()->json([
            'tenants' => TenantResource::collection($tenants->items()),
            'meta' => [
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
                'per_page' => $tenants->perPage(),
                'total' => $tenants->total(),
            ],
        ]);
    }

    /**
     * Get a single tenant.
     *
     * @authenticated
     *
     * @urlParam id string required The tenant ID. Example: tenant-abc-123
     *
     * @responseField tenant object The tenant resource.
     */
    public function show(string $id)
    {
        $tenant = Tenant::withTrashed()->with(['domains', 'plan.featureGates'])->findOrFail($id);

        return response()->json([
            'tenant' => new TenantResource($tenant),
        ]);
    }

    /**
     * Create a new tenant.
     *
     * Provisions a new tenant with database, migrations, and seed data.
     *
     * @authenticated
     *
     * @bodyParam name string required The tenant name. Example: Acme Corp
     * @bodyParam email string required Tenant admin email. Example: admin@acme.com
     * @bodyParam domain string required Primary domain. Example: acme.example.com
     * @bodyParam plan string optional Plan slug to assign. Example: pro
     *
     * @response 201 {"message":"Tenant created successfully","tenant":{"id":"...","name":"Acme Corp","email":"admin@acme.com","status":"Trial"}}
     */
    public function store(StoreTenantRequest $request)
    {
        $tenant = $this->tenantManager->provision($request->validated());

        return response()->json([
            'message' => 'Tenant created successfully',
            'tenant' => new TenantResource($tenant),
        ], 201);
    }

    /**
     * Update a tenant.
     *
     * Update tenant attributes, status, and/or plan. Status transition uses the state machine.
     *
     * @authenticated
     *
     * @urlParam id string required The tenant ID.
     *
     * @bodyParam name string optional Tenant name.
     * @bodyParam email string optional Tenant email.
     * @bodyParam status string optional New status (Active, Suspended, Deleted). Uses state machine.
     * @bodyParam plan_id integer optional New plan ID. Triggers plan change.
     *
     * @responseField message string Success message.
     * @responseField tenant object The updated tenant resource.
     *
     * @throws 422 If status transition or plan change is invalid.
     */
    public function update(string $id, UpdateTenantRequest $request)
    {
        $tenant = Tenant::with(['plan.featureGates'])->findOrFail($id);

        $data = $request->validated();
        $status = $data['status'] ?? null;
        $planId = $data['plan_id'] ?? null;
        unset($data['status'], $data['plan_id']);

        $fillable = ['name', 'email', 'domain'];
        foreach ($fillable as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $tenant->$key = $data[$key];
            }
        }

        if ($planId !== null && $planId != $tenant->plan_id) {
            $newPlan = Plan::findOrFail($planId);
            $this->tenantManager->changePlan($tenant, $newPlan);
            $needsSave = false;
        } else {
            $needsSave = true;
        }

        if ($status !== null) {
            try {
                $this->tenantManager->setStatus($tenant, $status);
            } catch (InvalidArgumentException $e) {
                \Log::warning('Invalid tenant status transition', [
                    'tenant_id' => $id,
                    'status' => $status,
                    'error' => $e->getMessage(),
                ]);

                return response()->json(['message' => 'Invalid status transition'], 422);
            }
        } elseif ($needsSave) {
            $tenant->save();
        }

        $tenant->refresh();

        return response()->json([
            'message' => 'Tenant updated successfully',
            'tenant' => new TenantResource($tenant),
        ]);
    }

    /**
     * Delete (suspend) a tenant.
     *
     * Soft-deletes the tenant and transitions to Suspended/Deleted state.
     *
     * @authenticated
     *
     * @urlParam id string required The tenant ID.
     *
     * @response 204 No content.
     *
     * @throws 422 If the tenant cannot be deleted in its current state.
     */
    public function destroy(string $id)
    {
        $tenant = Tenant::findOrFail($id);

        try {
            $this->tenantManager->delete($tenant);
        } catch (InvalidArgumentException $e) {
            \Log::warning('Failed to delete tenant', [
                'tenant_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Cannot delete tenant in its current state'], 422);
        }

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted tenant.
     *
     * @authenticated
     *
     * @urlParam id string required The tenant ID.
     *
     * @responseField message string Success message.
     * @responseField tenant object The restored tenant resource.
     *
     * @throws 422 If the tenant cannot be restored in its current state.
     */
    public function restore(string $id)
    {
        $tenant = Tenant::withTrashed()->findOrFail($id);

        try {
            $this->tenantManager->restore($tenant);
        } catch (InvalidArgumentException $e) {
            \Log::warning('Failed to restore tenant', [
                'tenant_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Cannot restore tenant in its current state'], 422);
        }

        return response()->json(['message' => 'Tenant restored successfully', 'tenant' => new TenantResource($tenant)]);
    }

    /**
     * Perform bulk operations on tenants.
     *
     * Supported actions: suspend, activate, delete, restore, change_plan, extend_trial.
     *
     * @authenticated
     *
     * @bodyParam tenant_ids string[] required Array of tenant IDs. Example: ["tenant-1","tenant-2"]
     * @bodyParam action string required Action to perform. Example: suspend
     * @bodyParam payload object optional Additional payload for the action.
     * @bodyParam payload.plan_id integer required_if action=change_plan Target plan ID.
     * @bodyParam payload.days integer required_if action=extend_trial Number of days to extend trial (1-365).
     *
     * @responseField total integer Total number of tenants processed.
     * @responseField succeeded integer Number of successful operations.
     * @responseField failed integer Number of failed operations.
     * @responseField results object[] Per-tenant results with status and optional error.
     */
    public function bulkOperation(BulkTenantOperationRequest $request)
    {
        $validated = $request->validated();
        $action = $validated['action'];
        $payload = $validated['payload'] ?? [];
        $tenantIds = $validated['tenant_ids'];
        $adminUser = auth('admin')->user();

        $tenants = Tenant::withTrashed()
            ->with(['plan.featureGates', 'subscriptions', 'activeSubscription'])
            ->whereIn('id', $tenantIds)
            ->get()
            ->keyBy('id');

        if ($action === 'change_plan') {
            $newPlan = Plan::findOrFail($payload['plan_id']);
        }

        $results = [];
        $succeeded = 0;
        $failed = 0;

        foreach ($tenantIds as $id) {
            $needsTransaction = $action !== 'delete';

            if ($needsTransaction) {
                DB::beginTransaction();
            }

            try {
                $tenant = $tenants->get($id);

                if (! $tenant) {
                    throw new \RuntimeException('Tenant not found');
                }

                match ($action) {
                    'suspend' => $this->tenantManager->suspend($tenant),
                    'activate' => $this->tenantManager->activate($tenant),
                    'delete' => $this->tenantManager->delete($tenant),
                    'restore' => $this->tenantManager->restore($tenant),
                    'change_plan' => $this->tenantManager->changePlan($tenant, $newPlan),
                    'extend_trial' => $this->extendTrial($tenant, $payload['days']),
                };

                activity($action === 'delete' ? 'tenant' : 'tenant')
                    ->performedOn($tenant)
                    ->causedBy($adminUser)
                    ->withProperties(['tenant_name' => $tenant->name, 'action' => $action, 'payload' => $payload])
                    ->log("Bulk {$action} for tenant {$tenant->name}");

                if ($needsTransaction) {
                    DB::commit();
                }

                $results[] = ['tenant_id' => $id, 'status' => 'success'];
                $succeeded++;
            } catch (\Throwable $e) {
                if ($needsTransaction) {
                    DB::rollBack();
                }
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

    /**
     * Get tenant database info.
     *
     * @authenticated
     *
     * @urlParam id string required The tenant ID.
     *
     * @responseField database.name string The tenant database name.
     * @responseField database.connection string The database driver.
     */
    public function database(string $id)
    {
        $tenant = Tenant::findOrFail($id);
        $db = $tenant->database();
        $dbName = $db->getName();

        $redacted = substr($dbName, 0, 6).'***'.substr($dbName, -4);

        return response()->json([
            'database' => [
                'name' => $redacted,
                'connection' => $db->connection()['driver'] ?? 'mysql',
            ],
        ]);
    }

    /**
     * Run migrations for a tenant.
     *
     * @authenticated
     *
     * @urlParam id string required The tenant ID.
     *
     * @responseField message string Status message.
     * @responseField output string Console output from the migration command.
     * @responseField exit integer Exit code (0 = success).
     *
     * @throws 500 If migration fails.
     */
    public function migrate(string $id)
    {
        try {
            $tenant = Tenant::findOrFail($id);

            $result = $this->tenantManager->migrateTenant($tenant);

            $redactedOutput = preg_replace(
                '/[A-Za-z]:(?:\\\\[^\\\\\s]+)+|(?:\/[^\s]+)+\.php/i',
                '[redacted]',
                $result['output']
            );

            activity('tenant')
                ->performedOn($tenant)
                ->causedBy(auth('admin')->user())
                ->withProperties(['tenant_name' => $tenant->name])
                ->log("Ran migrations for tenant {$tenant->name}");

            $exitCode = $result['exit_code'];

            return response()->json([
                'message' => $exitCode === 0 ? 'Migrations executed successfully' : 'Migrations completed with warnings',
                'output' => $redactedOutput,
                'exit' => $exitCode,
            ]);
        } catch (\Exception $e) {
            \Log::error('Tenant migration failed', [
                'tenant_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Migration failed',
                'error' => 'An internal error occurred. Please check the logs for details.',
                'output' => '',
            ], 500);
        }
    }

    /**
     * Change a tenant's plan.
     *
     * @authenticated
     *
     * @urlParam id string required The tenant ID.
     *
     * @bodyParam plan_id integer required The target plan ID. Example: 2
     *
     * @responseField message string Success message.
     * @responseField tenant object The tenant resource with updated plan.
     *
     * @throws 422 If the plan change is invalid.
     */
    public function changePlan(ChangeTenantPlanRequest $request, string $id)
    {
        $tenant = Tenant::with(['plan.featureGates'])->findOrFail($id);
        $newPlan = Plan::with('featureGates')->findOrFail($request->validated('plan_id'));
        $oldPlanName = $tenant->plan?->name ?? 'None';

        try {
            $this->tenantManager->changePlan($tenant, $newPlan);
        } catch (InvalidArgumentException $e) {
            \Log::warning('Failed to change tenant plan', [
                'tenant_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Cannot change plan in the current state'], 422);
        }

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

    /**
     * List all available plans.
     *
     * Cached for 1 hour.
     *
     * @authenticated
     *
     * @responseField plans object[] List of plan resources.
     */
    public function plans()
    {
        $plans = Cache::remember('admin_plans_list', 3600, fn () => Plan::with('featureGates')->take(100)->get());

        return response()->json([
            'plans' => PlanResource::collection($plans),
        ]);
    }

    private function extendTrial(Tenant $tenant, int $days): void
    {
        $this->tenantManager->extendTrial($tenant, $days);
    }
}
