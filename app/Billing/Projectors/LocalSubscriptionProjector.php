<?php

declare(strict_types=1);

namespace App\Billing\Projectors;

use App\Billing\Events\PlanChanged;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Shared\Contracts\StripePricePlanResolverInterface;
use App\Shared\Contracts\TenantLifecycleInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors verified Stripe subscription events into the app's local entitlement
 * projection. Cashier remains the owner of Stripe subscription state.
 */
class LocalSubscriptionProjector
{
    private const HANDLED_TYPES = [
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
    ];

    public function __construct(
        private StripePricePlanResolverInterface $plans,
        private TenantLifecycleInterface $lifecycle,
    ) {}

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

        $object = $this->stripeObject($event);
        $customerId = $this->identifier($object['customer'] ?? null);
        $subscriptionId = $this->identifier($object['id'] ?? null);
        $priceId = $this->priceId($object);

        if ($customerId === null || $subscriptionId === null) {
            return;
        }

        $tenant = Tenant::query()->where('stripe_customer_id', $customerId)->first();

        if ($tenant === null) {
            return;
        }

        $plan = $this->plans->resolve($priceId);

        if ($plan === null) {
            Log::warning('Stripe subscription price has no local plan mapping', [
                'tenant_id' => $tenant->id,
                'stripe_subscription_id' => $subscriptionId,
                'stripe_price' => $priceId,
            ]);

            return;
        }

        $status = $this->localStatus((string) ($object['status'] ?? ''));
        $subscription = Subscription::query()
            ->firstOrNew(['stripe_subscription_id' => $subscriptionId]);

        $subscription->tenant_id = $tenant->id;
        $subscription->plan_id = $plan->id;
        $subscription->starts_at = $this->timestamp(
            $object['current_period_start'] ?? $object['created'] ?? null
        ) ?? now();
        $subscription->ends_at = $this->timestamp(
            $object['canceled_at']
            ?? $object['trial_end']
            ?? $object['current_period_end']
            ?? null
        );
        $subscription->status = $status;
        $subscription->save();

        if (! in_array($status, ['active', 'trial'], true) || $tenant->plan_id === $plan->id) {
            return;
        }

        $oldPlan = $tenant->plan;
        $tenant->plan_id = $plan->id;
        $tenant->save();
        $this->lifecycle->flushCache($tenant);

        if ($oldPlan !== null) {
            event(new PlanChanged($tenant, $oldPlan, $plan));
        }
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function stripeObject(array $event): array
    {
        $payload = (array) ($event['payload'] ?? []);
        $data = (array) ($payload['data'] ?? []);
        $object = (array) ($data['object'] ?? []);

        return $object !== [] ? $object : (array) ($event['object'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function priceId(array $object): string
    {
        $items = (array) ($object['items'] ?? []);
        $first = (array) ((array_values((array) ($items['data'] ?? []))[0]) ?? []);
        $price = (array) ($first['price'] ?? []);

        return (string) ($price['id'] ?? '');
    }

    private function localStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'active' => 'active',
            'trialing' => 'trial',
            'past_due', 'incomplete', 'paused' => 'pending',
            'canceled', 'unpaid' => 'cancelled',
            'incomplete_expired' => 'expired',
            default => 'pending',
        };
    }

    private function timestamp(mixed $value): ?Carbon
    {
        return is_numeric($value) ? Carbon::createFromTimestamp((int) $value) : null;
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
}
