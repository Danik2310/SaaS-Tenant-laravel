<?php

declare(strict_types=1);

namespace App\Billing\Http\Controllers\Admin;

use App\Billing\Projectors\TenantPaymentCardProjector;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TenantPaymentCardRequest;
use App\Models\Tenant;
use App\Shared\Contracts\PaymentGatewayInterface;
use Illuminate\Http\JsonResponse;

/**
 * Admin actions for the tenant registry's masked card projection.
 *
 * Cards are always managed in Stripe's Customer Portal. This controller can
 * only refresh the local masked summary or hand off to the hosted portal.
 */
class TenantPaymentCardController extends Controller
{
    public function refresh(
        TenantPaymentCardRequest $request,
        Tenant $tenant,
        TenantPaymentCardProjector $projector,
    ): JsonResponse {
        $this->assertActive($tenant);
        $this->assertLinked($tenant);

        $projector->sync($tenant);

        return response()->json([
            'message' => 'Payment card refreshed.',
            'payment_card' => $this->paymentCard($tenant),
        ]);
    }

    public function portal(
        TenantPaymentCardRequest $request,
        Tenant $tenant,
        PaymentGatewayInterface $gateway,
    ): JsonResponse {
        $this->assertActive($tenant);
        $this->assertLinked($tenant);

        $session = $gateway->createCustomerPortalSession((string) $tenant->stripe_customer_id, [
            'return_url' => route('admin.dashboard').'?view=tenants',
        ]);

        return response()->json([
            'message' => 'Stripe Customer Portal session created.',
            'url' => (string) ($session['url'] ?? ''),
        ]);
    }

    private function assertActive(Tenant $tenant): void
    {
        if ($tenant->trashed()) {
            abort(404, 'Tenant not found.');
        }
    }

    private function assertLinked(Tenant $tenant): void
    {
        if ($tenant->stripe_customer_id === null || $tenant->stripe_customer_id === '') {
            abort(422, 'This tenant has no Stripe customer yet.');
        }
    }

    /**
     * @return array{linked: bool, brand: string|null, last4: string|null, exp_month: int|null, exp_year: int|null, card_count: int, synced_at: string|null}
     */
    private function paymentCard(Tenant $tenant): array
    {
        return [
            'linked' => true,
            'brand' => $tenant->pm_type,
            'last4' => $tenant->pm_last_four,
            'exp_month' => $tenant->pm_exp_month,
            'exp_year' => $tenant->pm_exp_year,
            'card_count' => (int) ($tenant->pm_card_count ?? 0),
            'synced_at' => $tenant->pm_synced_at?->toISOString(),
        ];
    }
}
