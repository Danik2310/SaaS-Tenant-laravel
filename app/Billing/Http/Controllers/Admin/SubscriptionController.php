<?php

namespace App\Billing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\Request;

/**
 * @group Subscription Management
 *
 * Read-only APIs for viewing tenant subscriptions in the admin panel.
 * Subscriptions are created and managed via the TenantManager during provisioning and plan changes.
 */
class SubscriptionController extends Controller
{
    /**
     * List all subscriptions.
     *
     * Paginated list with optional filtering by status, plan, or tenant name.
     *
     * @authenticated
     *
     * @queryParam status string Filter by status (active, cancelled, pending, expired). Example: active
     * @queryParam plan_id integer Filter by plan ID. Example: 2
     * @queryParam plan_name string Filter by plan name. Example: Pro
     * @queryParam tenant_name string Filter by tenant name. Example: Acme
     * @queryParam search string Search by plan name or tenant name. Example: Acme
     * @queryParam sort string Sort by (tenant_name, plan_name, status, created_at). Example: tenant_name
     * @queryParam order string Sort order (asc, desc). Example: asc
     * @queryParam per_page integer Results per page (max 100). Example: 15
     */
    public function index(Request $request)
    {
        $query = Subscription::with(['tenant', 'plan']);

        if ($status = $request->query('status')) {
            $query->where('subscriptions.status', $status);
        }

        if ($planId = $request->query('plan_id')) {
            $query->where('subscriptions.plan_id', $planId);
        }

        if ($planName = $request->query('plan_name')) {
            $query->whereHas('plan', fn ($q) => $q->where('name', $planName));
        }

        if ($tenantName = $request->query('tenant_name')) {
            $query->whereHas('tenant', fn ($q) => $q->where('name', $tenantName));
        }

        if ($search = $request->query('search')) {
            $search = str_replace(['%', '_'], ['\%', '\_'], $search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('plan', fn ($pq) => $pq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('tenant', fn ($tq) => $tq->where('name', 'like', "%{$search}%"));
            });
        }

        $sortParam = $request->query('sort', 'created_at');
        $order = $request->query('order', 'desc') === 'asc' ? 'asc' : 'desc';

        match ($sortParam) {
            'tenant_name' => $query->leftJoin('tenants', 'tenants.id', '=', 'subscriptions.tenant_id')
                ->select('subscriptions.*')
                ->orderBy('tenants.name', $order),
            'plan_name' => $query->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
                ->select('subscriptions.*')
                ->orderBy('plans.name', $order),
            'plan_price' => $query->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
                ->select('subscriptions.*')
                ->orderBy('plans.price', $order),
            default => $query->orderBy(in_array($sortParam, ['status', 'starts_at', 'ends_at', 'created_at']) ? "subscriptions.{$sortParam}" : 'subscriptions.created_at', $order),
        };

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
}
