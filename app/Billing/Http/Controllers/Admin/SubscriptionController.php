<?php

namespace App\Billing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubscriptionRequest;
use App\Http\Requests\Admin\UpdateSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Tenants\Contracts\TenantManagerInterface;
use Illuminate\Http\Request;
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
            $query->where('subscriptions.status', $status);
        }

        if ($planId = $request->query('plan_id')) {
            $query->where('plan_id', $planId);
        }

        if ($planName = $request->query('plan_name')) {
            $query->whereHas('plan', fn ($q) => $q->where('name', $planName));
        }

        if ($search = $request->query('search')) {
            $search = str_replace(['%', '_'], ['\%', '\_'], $search);
            $query->whereHas('plan', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        $sortableColumns = ['id', 'plan_id', 'status', 'starts_at', 'ends_at', 'created_at'];
        $sortParam = $request->query('sort', 'created_at');
        $sort = in_array($sortParam, $sortableColumns) ? $sortParam : 'created_at';
        $order = $request->query('order', 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sortParam === 'plan_name') {
            $query->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
                ->select('subscriptions.*')
                ->orderBy('plans.name', $order);
        } elseif ($sortParam === 'plan_price') {
            $query->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
                ->select('subscriptions.*')
                ->orderBy('plans.price', $order);
        } else {
            $query->orderBy($sort, $order);
        }

        $perPage = min((int) $request->integer('per_page', 5), 100);
        $subscriptions = $query->paginate($perPage);

        return response()->json([
            'subscriptions' => SubscriptionResource::collection($subscriptions->items()),
            'total' => $subscriptions->total(),
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
        }

        if (isset($validated['plan_id'])) {
            $newPlan = Plan::findOrFail($validated['plan_id']);
            $tenant = Tenant::findOrFail($subscription->tenant_id);

            try {
                $this->tenantManager->changePlan($tenant, $newPlan);
            } catch (\InvalidArgumentException $e) {
                return response()->json([
                    'message' => 'Cannot change plan: '.$e->getMessage(),
                ], 422);
            }

            $subscription = $subscription->fresh();
        }

        $subscription->load(['tenant', 'plan']);

        return response()->json([
            'message' => 'Subscription updated successfully',
            'subscription' => new SubscriptionResource($subscription),
        ]);
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

        $subscription->delete();

        Log::info('Subscription deleted', [
            'subscription_id' => $id,
            'tenant_id' => $subscription->tenant_id,
        ]);

        return response()->json(['message' => 'Subscription deleted successfully']);
    }
}
