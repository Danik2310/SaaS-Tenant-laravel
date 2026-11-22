<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface PaymentGatewayInterface
{
    public function charge(float $amount, array $options = []): array;

    public function refund(string $transactionId, float $amount): array;

    public function getCustomer(string $customerId): array;

    /**
     * Create a hosted checkout session (e.g. Stripe Checkout) for a one-off
     * payment. Returns the session id, the hosted checkout URL and status.
     *
     * @param  array{amount: float, currency?: string, name?: string, success_url: string, cancel_url: string, customer_email?: string|null, metadata?: array<string, scalar>}  $params
     * @return array{id: string, url: string, payment_status: string, currency: string, amount_total: int}
     */
    public function createCheckoutSession(array $params): array;

    /**
     * Retrieve a previously created checkout session to verify payment outcome
     * server-side before provisioning anything.
     *
     * @return array{id: string, payment_status: string, customer_email: string, metadata: array<string, string>}
     */
    public function retrieveCheckoutSession(string $sessionId): array;

    /**
     * Create the provider customer that owns the tenant's saved cards.
     *
     * @param  array{email?: string|null, name?: string|null, phone?: string|null, metadata?: array<string, scalar>}  $params
     * @return array{id: string}
     */
    public function createCustomer(array $params): array;

    /**
     * Update the provider customer's contact details after an explicit billing
     * profile save.
     *
     * @param  array{email?: string|null, name?: string|null, phone?: string|null}  $params
     * @return array{id: string}
     */
    public function updateCustomer(string $customerId, array $params): array;

    /**
     * Create a hosted, short-lived customer-portal session. The app never
     * renders or stores raw card numbers.
     *
     * @param  array{return_url: string}  $params
     * @return array{id: string, url: string}
     */
    public function createCustomerPortalSession(string $customerId, array $params): array;

    /**
     * Return masked, display-ready card metadata for a customer.
     *
     * @return array<int, array{id: string, brand: string, last4: string, exp_month: int, exp_year: int, is_default: bool}>
     */
    public function listPaymentMethods(string $customerId): array;

    /**
     * Verify a provider webhook signature and normalize the event for the
     * idempotent card-change notifier and Cashier subscription sync.
     *
     * `payload` is the full provider event and is optional for gateways that
     * only support card notifications.
     *
     * @return array{id: string, type: string, object: array<string, mixed>, previous: array<string, mixed>, payload?: array<string, mixed>}
     */
    public function verifyWebhookEvent(string $payload, string $signature): array;
}
