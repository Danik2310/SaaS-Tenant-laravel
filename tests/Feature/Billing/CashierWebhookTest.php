<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Billing\Webhooks\CashierSubscriptionWebhookAdapter;
use App\Models\CashierSubscription;
use App\Models\StripeWebhookEvent;
use App\Models\Tenant;
use App\Shared\Contracts\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CashierWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_events_are_projected_into_cashier_tables(): void
    {
        $tenant = Tenant::factory()->create([
            'stripe_customer_id' => 'cus_cashier_webhook',
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('verifyWebhookEvent')
            ->times(3)
            ->andReturn(
                $this->subscriptionEvent('evt_created', 'customer.subscription.created', [
                    'id' => 'sub_cashier',
                    'customer' => 'cus_cashier_webhook',
                    'status' => 'active',
                    'items' => $this->items('price_pro', 'prod_pro', 'si_pro'),
                ]),
                $this->subscriptionEvent('evt_updated', 'customer.subscription.updated', [
                    'id' => 'sub_cashier',
                    'customer' => 'cus_cashier_webhook',
                    'status' => 'past_due',
                    'items' => $this->items('price_pro_v2', 'prod_pro_v2', 'si_pro_v2'),
                ]),
                $this->subscriptionEvent('evt_deleted', 'customer.subscription.deleted', [
                    'id' => 'sub_cashier',
                    'customer' => 'cus_cashier_webhook',
                    'status' => 'canceled',
                    'items' => $this->items('price_pro_v2', 'prod_pro_v2', 'si_pro_v2'),
                ]),
            );
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        $headers = ['Stripe-Signature' => 't=test,v1=signature'];

        $this->postJson('/api/v1/webhooks/stripe', [], $headers)->assertOk();
        $this->assertDatabaseHas('cashier_subscriptions', [
            'billing_customer_id' => $tenant->id,
            'stripe_id' => 'sub_cashier',
            'stripe_status' => 'active',
            'stripe_price' => 'price_pro',
        ], 'mysql_central');
        $this->assertDatabaseHas('cashier_subscription_items', [
            'stripe_id' => 'si_pro',
            'stripe_price' => 'price_pro',
            'stripe_product' => 'prod_pro',
        ], 'mysql_central');

        $this->postJson('/api/v1/webhooks/stripe', [], $headers)->assertOk();
        $this->assertDatabaseHas('cashier_subscriptions', [
            'stripe_id' => 'sub_cashier',
            'stripe_status' => 'past_due',
            'stripe_price' => 'price_pro_v2',
        ], 'mysql_central');
        $this->assertDatabaseMissing('cashier_subscription_items', [
            'stripe_id' => 'si_pro',
        ], 'mysql_central');
        $this->assertDatabaseHas('cashier_subscription_items', [
            'stripe_id' => 'si_pro_v2',
            'stripe_price' => 'price_pro_v2',
        ], 'mysql_central');

        $this->postJson('/api/v1/webhooks/stripe', [], $headers)->assertOk();
        $subscription = CashierSubscription::query()->where('stripe_id', 'sub_cashier')->firstOrFail();
        $this->assertNotNull($subscription->ends_at);
    }

    public function test_subscription_event_for_unknown_customer_is_ignored(): void
    {
        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('verifyWebhookEvent')
            ->once()
            ->andReturn($this->subscriptionEvent('evt_unknown', 'customer.subscription.created', [
                'id' => 'sub_unknown',
                'customer' => 'cus_not_ours',
                'status' => 'active',
                'items' => $this->items('price_pro', 'prod_pro', 'si_unknown'),
            ]));
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        $this->postJson('/api/v1/webhooks/stripe', [], ['Stripe-Signature' => 't=test,v1=signature'])
            ->assertOk();

        $this->assertDatabaseMissing('cashier_subscriptions', [
            'stripe_id' => 'sub_unknown',
        ], 'mysql_central');
        $this->assertDatabaseHas('stripe_webhook_events', [
            'id' => 'evt_unknown',
            'type' => 'customer.subscription.created',
        ], 'mysql_central');
    }

    public function test_failed_subscription_processing_is_retried_after_ledger_rollback(): void
    {
        Tenant::factory()->create(['stripe_customer_id' => 'cus_cashier_webhook']);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('verifyWebhookEvent')
            ->twice()
            ->andReturn($this->subscriptionEvent('evt_retry', 'customer.subscription.created', [
                'id' => 'sub_retry',
                'customer' => 'cus_cashier_webhook',
                'status' => 'active',
                'items' => $this->items('price_pro', 'prod_pro', 'si_retry'),
            ]));
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        $attempts = 0;
        $cashier = Mockery::mock(CashierSubscriptionWebhookAdapter::class);
        $cashier->shouldReceive('handles')->twice()->andReturn(true);
        $cashier->shouldReceive('handle')->twice()->andReturnUsing(function () use (&$attempts): void {
            $attempts++;

            if ($attempts === 1) {
                throw new RuntimeException('Cashier sync failed.');
            }
        });
        $this->app->instance(CashierSubscriptionWebhookAdapter::class, $cashier);

        $headers = ['Stripe-Signature' => 't=test,v1=signature'];

        $this->postJson('/api/v1/webhooks/stripe', [], $headers)
            ->assertStatus(500)
            ->assertJson(['message' => 'Webhook processing failed; retry requested.']);
        $this->assertSame(0, StripeWebhookEvent::query()->where('id', 'evt_retry')->count());

        $this->postJson('/api/v1/webhooks/stripe', [], $headers)->assertOk();
        $this->assertSame(1, StripeWebhookEvent::query()->where('id', 'evt_retry')->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{id: string, type: string, object: array<string, mixed>, previous: array<string, mixed>, payload: array<string, mixed>}
     */
    private function subscriptionEvent(string $id, string $type, array $overrides): array
    {
        $object = array_merge([
            'id' => 'sub_default',
            'object' => 'subscription',
            'customer' => 'cus_cashier_webhook',
            'status' => 'active',
            'metadata' => [],
            'cancel_at_period_end' => false,
            'items' => $this->items('price_default', 'prod_default', 'si_default'),
        ], $overrides);

        return [
            'id' => $id,
            'type' => $type,
            'object' => $object,
            'previous' => [],
            'payload' => [
                'id' => $id,
                'object' => 'event',
                'type' => $type,
                'data' => [
                    'object' => $object,
                    'previous_attributes' => [],
                ],
            ],
        ];
    }

    /**
     * @return array{object: string, data: array<int, array<string, mixed>>}
     */
    private function items(string $price, string $product, string $itemId): array
    {
        return [
            'object' => 'list',
            'data' => [[
                'id' => $itemId,
                'object' => 'subscription_item',
                'quantity' => 1,
                'price' => [
                    'id' => $price,
                    'object' => 'price',
                    'product' => $product,
                ],
            ]],
        ];
    }
}
