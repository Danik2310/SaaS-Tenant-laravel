<?php

declare(strict_types=1);

namespace App\Billing\Projectors;

use App\Models\Tenant;
use App\Shared\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors masked Stripe card metadata into the central tenant registry.
 *
 * Stripe owns the cards; this projector only recomputes a display-safe summary
 * after a webhook or an explicit admin refresh. It never handles a PAN or CVC.
 */
class TenantPaymentCardProjector
{
    private const CARD_EVENTS = [
        'payment_method.attached',
        'payment_method.detached',
        'customer.updated',
    ];

    public function __construct(private readonly PaymentGatewayInterface $gateway) {}

    public function handles(string $type): bool
    {
        return in_array($type, self::CARD_EVENTS, true);
    }

    /**
     * @param  array{id: string, type: string, object: array<string, mixed>, previous: array<string, mixed>, payload?: array<string, mixed>}  $event
     */
    public function isCardChange(array $event): bool
    {
        $type = (string) ($event['type'] ?? '');

        if ($type === 'payment_method.attached' || $type === 'payment_method.detached') {
            return true;
        }

        if ($type !== 'customer.updated') {
            return false;
        }

        $object = (array) ($event['object'] ?? []);
        $previous = (array) ($event['previous'] ?? []);

        return $this->defaultPaymentMethodId($object) !== $this->defaultPaymentMethodId($previous);
    }

    /**
     * @param  array{id: string, type: string, object: array<string, mixed>, previous: array<string, mixed>, payload?: array<string, mixed>}  $event
     */
    public function customerIdFromEvent(array $event): ?string
    {
        $type = (string) ($event['type'] ?? '');
        $object = (array) ($event['object'] ?? []);

        if ($type === 'payment_method.attached' || $type === 'payment_method.detached') {
            return $this->identifier($object['customer'] ?? null);
        }

        if ($type === 'customer.updated') {
            return $this->identifier($object['id'] ?? null);
        }

        return null;
    }

    /**
     * @param  array{id: string, type: string, object: array<string, mixed>, previous: array<string, mixed>, payload?: array<string, mixed>}  $event
     */
    public function handle(array $event): void
    {
        if (! $this->handles((string) ($event['type'] ?? '')) || ! $this->isCardChange($event)) {
            return;
        }

        $customerId = $this->customerIdFromEvent($event);

        if ($customerId === null) {
            return;
        }

        $tenant = Tenant::where('stripe_customer_id', $customerId)->first();

        if ($tenant === null) {
            Log::warning('Ignoring card webhook for an unknown Stripe customer', [
                'event_type' => $event['type'] ?? 'unknown',
            ]);

            return;
        }

        $type = (string) ($event['type'] ?? '');

        if ($type === 'payment_method.attached' || $type === 'payment_method.detached') {
            // These events carry enough masked metadata for an immediate update
            // and must not require a gateway call. A full recompute happens on
            // customer.updated and on explicit admin refresh.
            $attributes = $this->projectedAttributesFromEvent(
                $event,
                (int) ($tenant->pm_card_count ?? 0),
            );
            $attributes['pm_synced_at'] = now();

            $tenant->forceFill($attributes)->saveQuietly();

            return;
        }

        $this->sync($tenant);
    }

    /**
     * Recompute the masked projection from the gateway's card list.
     */
    public function sync(Tenant $tenant): void
    {
        $customerId = (string) ($tenant->stripe_customer_id ?? '');

        if ($customerId === '') {
            return;
        }

        $attributes = $this->projectedCardAttributes(
            $this->gateway->listPaymentMethods($customerId),
        );
        $attributes['pm_synced_at'] = now();

        $tenant->forceFill($attributes)->saveQuietly();
    }

    /**
     * Pure mapper used by both `sync()` and the unit tests.
     *
     * @param  array<int, array{id: string, brand: string, last4: string, exp_month: int, exp_year: int, is_default: bool}>  $cards
     * @return array{pm_type: string|null, pm_last_four: string|null, pm_exp_month: int|null, pm_exp_year: int|null, pm_card_count: int}
     */
    public function projectedCardAttributes(array $cards): array
    {
        $cards = array_values($cards);
        $default = null;

        foreach ($cards as $card) {
            if (($card['is_default'] ?? false) === true) {
                $default = $card;

                break;
            }
        }

        $default ??= $cards[0] ?? null;

        return [
            'pm_type' => $default === null ? null : self::stringOrNull($default['brand'] ?? null),
            'pm_last_four' => $default === null ? null : self::stringOrNull($default['last4'] ?? null),
            'pm_exp_month' => $default === null ? null : self::positiveIntOrNull($default['exp_month'] ?? null),
            'pm_exp_year' => $default === null ? null : self::positiveIntOrNull($default['exp_year'] ?? null),
            'pm_card_count' => count($cards),
        ];
    }

    /**
     * Pure mapper for the masked payload carried by Stripe card events.
     *
     * @param  array{id: string, type: string, object: array<string, mixed>, previous: array<string, mixed>, payload?: array<string, mixed>}  $event
     * @return array{pm_type: string|null, pm_last_four: string|null, pm_exp_month: int|null, pm_exp_year: int|null, pm_card_count: int}
     */
    public function projectedAttributesFromEvent(array $event, ?int $currentCardCount = null): array
    {
        if ((string) ($event['type'] ?? '') === 'payment_method.detached') {
            return [
                'pm_type' => null,
                'pm_last_four' => null,
                'pm_exp_month' => null,
                'pm_exp_year' => null,
                'pm_card_count' => 0,
            ];
        }

        $object = (array) ($event['object'] ?? []);
        $card = (array) ($object['card'] ?? []);

        return [
            'pm_type' => self::stringOrNull($card['brand'] ?? null),
            'pm_last_four' => self::stringOrNull($card['last4'] ?? null),
            'pm_exp_month' => self::positiveIntOrNull($card['exp_month'] ?? null),
            'pm_exp_year' => self::positiveIntOrNull($card['exp_year'] ?? null),
            'pm_card_count' => max(1, ($currentCardCount ?? 0) + 1),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function defaultPaymentMethodId(array $data): ?string
    {
        $settings = (array) ($data['invoice_settings'] ?? []);

        return $this->identifier($settings['default_payment_method'] ?? null);
    }

    private function identifier(mixed $value): ?string
    {
        if (is_array($value)) {
            return isset($value['id']) ? (string) $value['id'] : null;
        }

        if (is_object($value)) {
            return isset($value->id) ? (string) $value->id : null;
        }

        return is_scalar($value) && $value !== '' ? (string) $value : null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function positiveIntOrNull(mixed $value): ?int
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }
}
