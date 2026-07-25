<?php

namespace App\Billing\Http\Controllers\Admin;

use App\Billing\Events\PlanChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubscriptionRequest;
use App\Http\Requests\Admin\UpdateSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Tenants\Contracts\TenantManagerInterface;
use App\Tenants\States\TenantStateManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @group Subscription Management
 *
 * APIs for managing tenant subscriptions in the admin panel.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private TenantManagerInterface $tenantManager,
    ) {}

    /**
     * List all subscriptions.
     *
     * Paginated list with optional filtering by status, plan, or tenant search.
     *
     * @authenticated
     *
     * @queryParam status string Filter by status (active, cancelled, pending). Example: active
     * @queryParam plan_id integer Filter by plan ID. Example: 1
     * @queryParam search string Search by tenant name or email. Example: Acme
     */
    public function index(Request $request)
    {
        $query = Subscription::with(['tenant', 'plan']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($planId = $request->query('plan_id')) {
            $query->where('plan_id', $planId);
        }

        if ($search = $request->query('search')) {
            $search = str_replace(['%', '_'], ['\%', '\_'], $search);
            $query->whereHas('tenant', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        $subscriptions = $query->orderBy('created_at', 'desc')->paginate(5);

        return response()->json([
            'subscriptions' => SubscriptionResource::collection($subscriptions->items()),
            'meta' => [
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
                'per_page' => $subscriptions->perPage(),
                'total' => $subscriptions->total(),
            ],
        ]);
    }

    /**
     * Get a single subscription.
     *
     * @authenticated
     *
     * @urlParam id string required The subscription ID.
     */
    public function show(string $id)
    {
        $sub = Subscription::with(['tenant', 'plan'])->findOrFail($id);

        return response()->json([
            'subscription' => new SubscriptionResource($sub),
        ]);
    }

    /**
     * Create a subscription.
     *
     * Creates a new subscription for a tenant, cancelling any active subscription.
     *
     * @authenticated
     *
     * @bodyParam tenant_id string required The tenant ID.
     * @bodyParam plan_id integer required The plan ID.
     * @bodyParam status string required Subscription status (active, pending, cancelled).
     * @bodyParam ends_at string optional End date (Y-m-d).
     * @bodyParam starts_at string optional Start date (Y-m-d).
     *
     * @response 201 {"message":"Subscription created successfully","subscription":{"id":"...","status":"active"}}
     */
    public function store(StoreSubscriptionRequest $request)
    {
        $validated = $request->validated();

        $tenant = Tenant::findOrFail($validated['tenant_id']);
        $plan = Plan::findOrFail($validated['plan_id']);

        $subscription = $this->tenantManager->createSubscription(
            $tenant,
            $plan,
            $validated['status'],
            isset($validated['ends_at']) ? now()->parse($validated['ends_at']) : null,
            isset($validated['starts_at']) ? now()->parse($validated['starts_at']) : null,
        );

        return response()->json([
            'message' => 'Subscription created successfully',
            'subscription' => new SubscriptionResource($subscription->load(['tenant', 'plan'])),
        ], 201);
    }

    /**
     * Update a subscription.
     *
     * @authenticated
     *
     * @urlParam id string required The subscription ID.
     *
     * @bodyParam status string Subscription status (active, pending, cancelled, expired).
     * @bodyParam plan_id integer The plan ID to switch to.
     */
    public function update(UpdateSubscriptionRequest $request, string $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $subscription = Subscription::with('tenant.plan')->findOrFail($id);
            $validated = $request->validated();

            if (isset($validated['status'])) {
                $allowedTransitions = [
                    'active' => ['pending', 'cancelled', 'expired'],
                    'pending' => ['active'],
                    'cancelled' => ['active'],
                    'expired' => ['active'],
                ];

                $currentStatus = $subscription->status;
                $newStatus = $validated['status'];
                $allowed = $allowedTransitions[$currentStatus] ?? [];

                if (! in_array($newStatus, $allowed, true)) {
                    return response()->json([
                        'message' => "Cannot transition subscription from '{$currentStatus}' to '{$newStatus}'",
                    ], 422);
                }

                $subscription->status = $newStatus;
                $subscription->save();
                unset($validated['status']);
            }

            if (isset($validated['plan_id'])) {
                $newPlan = Plan::findOrFail($validated['plan_id']);

                $tenant = Tenant::lockForUpdate()->findOrFail($subscription->tenant_id);
                $oldPlan = $tenant->plan;

                $subscription->update([
                    'status' => 'cancelled',
                    'ends_at' => now()->subDay(),
                ]);

                Subscription::createForTenant($tenant, $newPlan, 'active');

                $tenant->plan_id = $newPlan->id;
                $tenant->save();

                event(new PlanChanged($tenant, $oldPlan, $newPlan));
                TenantStateManager::flushTenantCache($tenant);

                $tenant = $tenant->fresh();
            }

            $subscription->fresh()->load(['tenant', 'plan']);

            return response()->json([
                'message' => 'Subscription updated successfully',
                'subscription' => new SubscriptionResource($subscription),
            ]);
        });
    }

    /**
     * Cancel a subscription.
     *
     * Subscriptions are financial records and are cancelled rather than deleted.
     *
     * @authenticated
     *
     * @urlParam id string required The subscription ID.
     *
     * @response 200 {"message":"Subscription cancelled successfully"}
     */
    public function destroy(string $id)
    {
        $subscription = Subscription::findOrFail($id);

        if ($subscription->status === 'cancelled') {
            return response()->json(['message' => 'Subscription is already cancelled.'], 200);
        }

        $subscription->update([
            'status' => 'cancelled',
            'ends_at' => now(),
        ]);

        Log::info('Subscription cancelled', [
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
        ]);

        return response()->json(['message' => 'Subscription cancelled successfully']);
    }
}
