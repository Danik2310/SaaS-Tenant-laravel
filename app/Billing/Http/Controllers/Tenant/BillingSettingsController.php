<?php

declare(strict_types=1);

namespace App\Billing\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Billing\UpdateBillingProfileRequest;
use App\Models\Tenant;
use App\Shared\Contracts\PaymentGatewayInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * Tenant-facing billing profile and Stripe-hosted card management.
 *
 * Card data is never handled here: the app only keeps the Stripe customer id
 * and displays the masked metadata returned by the gateway.
 */
class BillingSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $tenant = $this->currentTenant();
        $cards = [];
        $cardError = null;

        if ($tenant->stripe_customer_id !== null) {
            try {
                /** @var PaymentGatewayInterface $gateway */
                $gateway = app(PaymentGatewayInterface::class);
                $cards = $gateway->listPaymentMethods($tenant->stripe_customer_id);
            } catch (Throwable $exception) {
                report($exception);
                $cardError = 'Saved cards could not be loaded right now.';
            }
        }

        return Inertia::render('Tenant/Settings/Billing', [
            'billing' => $this->billingProps($tenant),
            'subscription' => $this->subscriptionProps($tenant),
            'cards' => $cards,
            'cardsEnabled' => (string) config('services.stripe.secret_key') !== '',
            'cardError' => $cardError,
            'portalResult' => $request->query('portal'),
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    public function update(UpdateBillingProfileRequest $request): RedirectResponse
    {
        $tenant = $this->currentTenant();
        $data = $request->validated();

        if ($tenant->stripe_customer_id !== null) {
            $billingEmail = (string) ($data['billing_email'] ?? '');

            if ($billingEmail !== ($tenant->billing_email ?? $tenant->email)) {
                try {
                    /** @var PaymentGatewayInterface $gateway */
                    $gateway = app(PaymentGatewayInterface::class);
                    $gateway->updateCustomer($tenant->stripe_customer_id, ['email' => $billingEmail]);
                } catch (Throwable $exception) {
                    report($exception);

                    return back()->withInput()->with('error', 'The billing email could not be updated with Stripe. Please try again.');
                }
            }
        }

        $tenant->fill($data)->save();

        return back()->with('status', 'Billing details updated.');
    }

    public function portal(): SymfonyResponse
    {
        $tenant = $this->currentTenant();
        $customerId = $tenant->stripe_customer_id;

        if ($customerId === null) {
            try {
                /** @var PaymentGatewayInterface $gateway */
                $gateway = app(PaymentGatewayInterface::class);
                $customer = $gateway->createCustomer([
                    'email' => $tenant->billing_email ?? $tenant->email,
                    'name' => $tenant->company_name ?: $tenant->name,
                    'phone' => $tenant->phone,
                    'metadata' => [
                        'tenant_id' => (string) $tenant->id,
                        'tenant_reference' => (string) $tenant->reference_id,
                    ],
                ]);
            } catch (Throwable $exception) {
                report($exception);

                return back()->with('error', 'Stripe customer setup is unavailable. Please try again.');
            }

            $customerId = (string) $customer['id'];
            $tenant->forceFill(['stripe_customer_id' => $customerId])->save();
        }

        try {
            /** @var PaymentGatewayInterface $gateway */
            $gateway = app(PaymentGatewayInterface::class);
            $session = $gateway->createCustomerPortalSession($customerId, [
                'return_url' => route('tenant.billing.settings', ['portal' => 'success']),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Stripe billing portal is unavailable. Please try again.');
        }

        return Inertia::location($session['url']);
    }

    private function currentTenant(): Tenant
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            abort(500, 'Tenant context is missing.');
        }

        return $tenant;
    }

    /**
     * @return array{plan_name: string, status: string, starts_at: string|null, ends_at: string|null}|null
     */
    private function subscriptionProps(Tenant $tenant): ?array
    {
        $subscription = $tenant->activeSubscription()
            ->with('plan')
            ->first() ?? $tenant->subscriptions()
            ->with('plan')
            ->orderByDesc('starts_at')
            ->first();

        if ($subscription === null) {
            return null;
        }

        return [
            'plan_name' => $subscription->plan?->name ?? 'Unknown plan',
            'status' => (string) $subscription->status,
            'starts_at' => $subscription->starts_at?->toFormattedDateString(),
            'ends_at' => $subscription->ends_at?->toFormattedDateString(),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function billingProps(Tenant $tenant): array
    {
        return [
            'billing_email' => $tenant->billing_email ?? $tenant->email,
            'company_name' => $tenant->company_name,
            'first_name' => $tenant->first_name,
            'last_name' => $tenant->last_name,
            'phone' => $tenant->phone,
            'address_line1' => $tenant->address_line1,
            'address_line2' => $tenant->address_line2,
            'city' => $tenant->city,
            'state' => $tenant->state,
            'postal_code' => $tenant->postal_code,
            'country' => $tenant->country,
            'tax_id' => $tenant->tax_id,
        ];
    }
}
