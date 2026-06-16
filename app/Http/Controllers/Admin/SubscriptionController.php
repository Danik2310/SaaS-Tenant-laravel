<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\TenantManagerInterface;
use App\Events\PlanChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubscriptionRequest;
use App\Http\Requests\Admin\UpdateSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\Request;

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
            $query->whereHas('tenant', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        $subscriptions = $query->orderBy('created_at', 'desc')->paginate(25);

        return response()->json([
            'data' => SubscriptionResource::collection($subscriptions->items()),
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
            'data' => new SubscriptionResource($sub),
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
     * @apiResource App\Http\Resources\SubscriptionResource
     *
     * @apiResourceModel App\Models\Subscription
     *
     * @response 201 {"message":"Subscription created successfully","data":{...}}
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
            'data' => new SubscriptionResource($subscription->load(['tenant', 'plan'])),
        ], 201);
    }

    /**
     * Update a subscription.
     *
     * @authenticated
     *
     * @urlParam id string required The subscription ID.
     */
    public function update(UpdateSubscriptionRequest $request, string $id)
    {
        $subscription = Subscription::with('tenant.plan')->findOrFail($id);
        $validated = $request->validated();

        $subscription->update($validated);

        if (isset($validated['plan_id']) && (int) $validated['plan_id'] !== $subscription->tenant->plan_id) {
            $tenant = $subscription->tenant;
            $oldPlan = $tenant->plan;
            $newPlan = Plan::findOrFail($validated['plan_id']);

            $tenant->plan_id = $newPlan->id;
            $tenant->save();

            if ($oldPlan && $oldPlan->id !== $newPlan->id) {
                event(new PlanChanged($tenant, $oldPlan, $newPlan));
            }
        }

        return response()->json([
            'message' => 'Subscription updated successfully',
            'data' => new SubscriptionResource($subscription->fresh()->load(['tenant', 'plan'])),
        ]);
    }

    /**
     * Delete a subscription.
     *
     * @authenticated
     *
     * @urlParam id string required The subscription ID.
     *
     * @response 204 No content.
     */
    public function destroy(string $id)
    {
        $subscription = Subscription::findOrFail($id);
        $subscription->delete();

        return response()->noContent();
    }
}
