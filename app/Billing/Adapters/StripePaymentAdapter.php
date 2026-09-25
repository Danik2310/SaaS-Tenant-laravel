<?php

declare(strict_types=1);

namespace App\Billing\Adapters;

use App\Billing\Contracts\PaymentGatewayInterface;
use Stripe\StripeClient;

class StripePaymentAdapter implements PaymentGatewayInterface
{
    public function __construct(private readonly StripeClient $stripe) {}

    public function charge(float $amount, array $options = []): array
    {
        $paymentIntent = $this->stripe->paymentIntents->create([
            'amount' => $this->toMinorUnits($amount),
            'currency' => $options['currency'] ?? 'usd',
            'automatic_payment_methods' => ['enabled' => true],
            'customer' => $options['customer'] ?? null,
            'metadata' => $options['metadata'] ?? [],
        ]);

        return $this->normalizePaymentIntent($paymentIntent);
    }

    public function createCheckoutSession(array $params): array
    {
        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => $params['success_url'],
            'cancel_url' => $params['cancel_url'],
            'customer_email' => $params['customer_email'] ?? null,
            'metadata' => $params['metadata'] ?? [],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $params['currency'] ?? 'usd',
                    'unit_amount' => $this->toMinorUnits((float) $params['amount']),
                    'product_data' => [
                        'name' => $params['name'] ?? 'Plan',
                    ],
                ],
            ]],
        ]);

        return $this->normalizeCheckoutSession($session);
    }

    public function retrieveCheckoutSession(string $sessionId): array
    {
        $session = $this->stripe->checkout->sessions->retrieve($sessionId);

        return $this->normalizeCheckoutSession($session);
    }

    public function refund(string $transactionId, float $amount): array
    {
        $refund = $this->stripe->refunds->create([
            'payment_intent' => $transactionId,
            'amount' => $this->toMinorUnits($amount),
        ]);

        return [
            'id' => $refund->id,
            'payment_intent' => $refund->payment_intent,
            'amount' => $refund->amount,
            'currency' => $refund->currency,
            'status' => $refund->status,
        ];
    }

    public function getCustomer(string $customerId): array
    {
        return $this->stripe->customers->retrieve($customerId)->toArray();
    }

    private function normalizePaymentIntent($paymentIntent): array
    {
        return [
            'id' => $paymentIntent->id,
            'status' => $paymentIntent->status,
            'amount' => $paymentIntent->amount,
            'currency' => $paymentIntent->currency,
            'client_secret' => $paymentIntent->client_secret,
            'customer' => $paymentIntent->customer,
        ];
    }

    private function normalizeCheckoutSession($session): array
    {
        return [
            'id' => (string) $session->id,
            'payment_status' => (string) $session->payment_status,
            'customer_email' => (string) ($session->customer_details->email ?? ''),
            'metadata' => $session->metadata ? $session->metadata->toArray() : [],
            'url' => (string) ($session->url ?? ''),
            'currency' => (string) ($session->currency ?? 'usd'),
            'amount_total' => $session->amount_total ?? 0,
        ];
    }

    private function toMinorUnits(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
