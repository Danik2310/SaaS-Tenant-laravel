<?php

declare(strict_types=1);

namespace App\Billing\Webhooks;

use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController;

/**
 * Feeds already-verified subscription events into Cashier's own webhook handler.
 *
 * Signature verification and event-id idempotency stay in the application's
 * central webhook controller; this adapter only translates the normalized event
 * back into the Stripe payload shape Cashier expects.
 */
class CashierSubscriptionWebhookAdapter
{
    private const HANDLED_TYPES = [
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
    ];

    public function handles(string $type): bool
    {
        return in_array($type, self::HANDLED_TYPES, true);
    }

    /**
     * @param  array{id: string, type: string, object: array<string, mixed>, previous: array<string, mixed>, payload?: array<string, mixed>}  $event
     */
    public function handle(array $event): void
    {
        $type = (string) ($event['type'] ?? '');

        if (! $this->handles($type)) {
            return;
        }

        $payload = (array) ($event['payload'] ?? []);

        if ($payload === []) {
            $payload = [
                'id' => (string) ($event['id'] ?? ''),
                'object' => 'event',
                'type' => $type,
                'data' => [
                    'object' => (array) ($event['object'] ?? []),
                    'previous_attributes' => (array) ($event['previous'] ?? []),
                ],
            ];
        }

        $payload['id'] = (string) ($payload['id'] ?? $event['id'] ?? '');
        $payload['type'] = (string) ($payload['type'] ?? $type);

        if (! isset($payload['data']) || ! is_array($payload['data'])) {
            $payload['data'] = [];
        }

        if (! isset($payload['data']['object']) || ! is_array($payload['data']['object'])) {
            $payload['data']['object'] = (array) ($event['object'] ?? []);
        }

        $request = Request::create(
            '/',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        (new WebhookController)->handleWebhook($request);
    }
}
