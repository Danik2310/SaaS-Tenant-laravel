<?php

declare(strict_types=1);

namespace App\Billing\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Billing\ListPaymentsRequest;
use App\Http\Resources\Tenant\Billing\SubscriptionPaymentResource;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Facades\Cache;

/**
 * @group Tenant Billing
 *
 * APIs for tenant billing and subscription management.
 */
class SubscriptionPaymentController extends Controller
{
    /**
     * Show payment history.
     *
     * Returns the current tenant's payment history, filtered and paginated.
     * The result is cached per tenant and flushed whenever a payment for that
     * tenant is created, updated or deleted.
     *
     * @authenticated
     *
     * @queryParam per_page int Number of results per page, between 5 and 50. Defaults to 15. Example: 15
     * @queryParam status string Filter by payment status (completed, pending, failed, refunded). Example: completed
     *
     * @responseField payments object Paginated list of SubscriptionPaymentResource items.
     */
    public function index(ListPaymentsRequest $request)
    {
        $tenantId = tenant()->id;

        $cacheKey = 'billing.payments.'.$tenantId.'.'.md5(serialize([
            $request->validated(),
            $request->input('page', 1),
        ]));

        $payments = Cache::tags([
            config('tenancy.cache.tag_base').$tenantId,
            'billing',
        ])->remember($cacheKey, 300, function () use ($request, $tenantId) {
            return $request->applyFilters(
                SubscriptionPayment::query()->where('tenant_id', $tenantId)
            )->paginate($request->perPage());
        });

        return inertia('Tenant/Billing/Payments', [
            'payments' => SubscriptionPaymentResource::collection($payments),
            'filters' => $request->validated(),
        ]);
    }
}
