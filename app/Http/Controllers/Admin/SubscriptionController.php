<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubscriptionRequest;
use App\Http\Requests\Admin\UpdateSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
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
            'subscriptions' => SubscriptionResource::collection($subscriptions->items()),
            'total' => $subscriptions->total(),
            'meta' => [
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
                'per_page' => $subscriptions->perPage(),
                'total' => $subscriptions->total(),
            ],
        ]);
    }

    public function show(string $id)
    {
        $sub = Subscription::with(['tenant', 'plan'])->findOrFail($id);

        return response()->json([
            'subscription' => new SubscriptionResource($sub),
        ]);
    }

    public function store(StoreSubscriptionRequest $request)
    {
        $tenant = Tenant::findOrFail($request->tenant_id);
        $plan = Plan::findOrFail($request->plan_id);

        $tenant->activeSubscription?->update([
            'status' => 'cancelled',
            'ends_at' => now()->subDay(),
        ]);

        $subscription = Subscription::createForTenant(
            $tenant,
            $plan,
            $request->status,
            $request->ends_at ? now()->parse($request->ends_at) : null,
            $request->starts_at ? now()->parse($request->starts_at) : null,
        );

        return response()->json([
            'message' => 'Subscription created successfully',
            'subscription' => new SubscriptionResource($subscription->load(['tenant', 'plan'])),
        ], 201);
    }

    public function update(UpdateSubscriptionRequest $request, string $id)
    {
        $subscription = Subscription::findOrFail($id);
        $subscription->update($request->validated());

        return response()->json([
            'message' => 'Subscription updated successfully',
            'subscription' => new SubscriptionResource($subscription->fresh()->load(['tenant', 'plan'])),
        ]);
    }

    public function destroy(string $id)
    {
        $subscription = Subscription::findOrFail($id);
        $subscription->delete();

        return response()->noContent();
    }
}
