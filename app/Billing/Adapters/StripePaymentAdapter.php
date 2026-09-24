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

    private function toMinorUnits(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
