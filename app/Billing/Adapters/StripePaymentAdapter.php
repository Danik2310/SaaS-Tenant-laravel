<?php

declare(strict_types=1);

namespace App\Billing\Adapters;

use App\Shared\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;
use Stripe\StripeClient;
use Stripe\Webhook;

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

    public function createCustomer(array $params): array
    {
        $customer = $this->stripe->customers->create($this->withoutNulls([
            'email' => $params['email'] ?? null,
            'name' => $params['name'] ?? null,
            'phone' => $params['phone'] ?? null,
            'metadata' => $params['metadata'] ?? [],
        ]));

        return ['id' => (string) $customer->id];
    }

    public function updateCustomer(string $customerId, array $params): array
    {
        $customer = $this->stripe->customers->update(
            $customerId,
            $this->withoutNulls([
                'email' => $params['email'] ?? null,
                'name' => $params['name'] ?? null,
                'phone' => $params['phone'] ?? null,
            ]),
        );

        return ['id' => (string) $customer->id];
    }

    public function createCustomerPortalSession(string $customerId, array $params): array
    {
        $session = $this->stripe->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $params['return_url'],
        ]);

        return [
            'id' => (string) $session->id,
            'url' => (string) $session->url,
        ];
    }

    public function listPaymentMethods(string $customerId): array
    {
        $customer = $this->stripe->customers->retrieve($customerId, [
            'expand' => ['invoice_settings.default_payment_method'],
        ]);

        $settings = $this->toArray($customer->invoice_settings ?? []);
        $default = $settings['default_payment_method'] ?? null;

        if (is_array($default)) {
            $default = (string) ($default['id'] ?? '');
        } elseif (is_object($default)) {
            $default = (string) ($default->id ?? '');
        } else {
            $default = (string) $default;
        }

        $response = $this->stripe->paymentMethods->all([
            'customer' => $customerId,
            'type' => 'card',
            'limit' => 100,
        ]);

        $cards = [];

        foreach ($response->data ?? [] as $paymentMethod) {
            $card = $this->toArray($paymentMethod->card ?? []);

            $cards[] = [
                'id' => (string) $paymentMethod->id,
                'brand' => ucfirst((string) ($card['brand'] ?? 'card')),
                'last4' => (string) ($card['last4'] ?? ''),
                'exp_month' => (int) ($card['exp_month'] ?? 0),
                'exp_year' => (int) ($card['exp_year'] ?? 0),
                'is_default' => (string) $paymentMethod->id === $default,
            ];
        }

        usort($cards, fn (array $a, array $b): int => (int) $b['is_default'] <=> (int) $a['is_default']);

        return $cards;
    }

    public function verifyWebhookEvent(string $payload, string $signature): array
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            throw new InvalidArgumentException('Stripe webhook secret is not configured.');
        }

        $event = Webhook::constructEvent($payload, $signature, $secret);

        return [
            'id' => (string) $event->id,
            'type' => (string) $event->type,
            'object' => $this->toArray($event->data->object ?? []),
            'previous' => $this->toArray($event->data->previous_attributes ?? []),
        ];
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

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function withoutNulls(array $params): array
    {
        return array_filter($params, fn ($value) => $value !== null);
    }

    private function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        return [];
    }

    private function toMinorUnits(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
