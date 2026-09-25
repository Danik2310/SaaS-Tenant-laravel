<?php

declare(strict_types=1);

namespace App\Billing\Contracts;

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
}
