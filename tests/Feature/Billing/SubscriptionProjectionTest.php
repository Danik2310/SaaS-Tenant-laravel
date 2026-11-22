<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Billing\Events\PlanChanged;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Shared\Contracts\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class SubscriptionProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_subscription_creates_local_projection_and_switches_plan(): void
    {
        $events = Event::fake([PlanChanged::class]);

        $oldPlan = Plan::factory()->create(['slug' => 'free']);
        $newPlan = Plan::factory()->create([
            'slug' => 'pro',
            'stripe_price_id' => 'price_pro',
        ]);
        $tenant = Tenant::factory()->create([
            'plan_id' => $oldPlan->id,
            'stripe_customer_id' => 'cus_projection',
        ]);

        $this->mockGateway($this->subscriptionEvent('evt_projection_created', 'customer.subscription.created', [
            'id' => 'sub_projection',
            'customer' => 'cus_projection',
            'status' => 'active',
            'current_period_start' => 1_760_000_000,
            'current_period_end' => 1_762_592_000,
            'items' => $this->items('price_pro', 'prod_pro', 'si_projection'),
        ]));

        $this->postJson('/api/v1/webhooks/stripe', [], ['Stripe-Signature' => 't=test,v1=signature'])
            ->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $newPlan->id,
            'stripe_subscription_id' => 'sub_projection',
            'status' => 'active',
        ], 'mysql_central');
        $this->assertSame($newPlan->id, $tenant->fresh()->plan_id);

        $events->assertDispatched(PlanChanged::class, function (PlanChanged $event) use ($tenant, $oldPlan, $newPlan): bool {
            return $event->tenant->id === $tenant->id
                && $event->oldPlan->id === $oldPlan->id
                && $event->newPlan->id === $newPlan->id;
        });
    }

    public function test_stripe_statuses_map_to_local_subscription_statuses(): void
    {
        $plan = Plan::factory()->create(['stripe_price_id' => 'price_status']);
        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
            'stripe_customer_id' => 'cus_status',
        ]);

        $events = [
            $this->subscriptionEvent('evt_trial', 'customer.subscription.updated', [
                'id' => 'sub_status',
                'customer' => 'cus_status',
                'status' => 'trialing',
                'trial_end' => 1_762_592_000,
                'items' => $this->items('price_status', 'prod_status', 'si_status'),
            ]),
            $this->subscriptionEvent('evt_past_due', 'customer.subscription.updated', [
                'id' => 'sub_status',
                'customer' => 'cus_status',
                'status' => 'past_due',
                'items' => $this->items('price_status', 'prod_status', 'si_status'),
            ]),
            $this->subscriptionEvent('evt_canceled', 'customer.subscription.deleted', [
                'id' => 'sub_status',
                'customer' => 'cus_status',
                'status' => 'canceled',
                'canceled_at' => 1_762_592_000,
                'items' => $this->items('price_status', 'prod_status', 'si_status'),
            ]),
        ];

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('verifyWebhookEvent')->times(3)->andReturn(...$events);
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        $headers = ['Stripe-Signature' => 't=test,v1=signature'];

        $this->postJson('/api/v1/webhooks/stripe', [], $headers)->assertOk();
        $this->assertSame('trial', Subscription::query()->where('stripe_subscription_id', 'sub_status')->value('status'));

        $this->postJson('/api/v1/webhooks/stripe', [], $headers)->assertOk();
        $this->assertSame('pending', Subscription::query()->where('stripe_subscription_id', 'sub_status')->value('status'));

        $this->postJson('/api/v1/webhooks/stripe', [], $headers)->assertOk();
        $this->assertSame('cancelled', Subscription::query()->where('stripe_subscription_id', 'sub_status')->value('status'));
    }

    public function test_unmapped_stripe_price_does_not_create_a_local_projection(): void
    {
        Tenant::factory()->create(['stripe_customer_id' => 'cus_unmapped']);

        $this->mockGateway($this->subscriptionEvent('evt_unmapped', 'customer.subscription.created', [
            'id' => 'sub_unmapped',
            'customer' => 'cus_unmapped',
            'status' => 'active',
            'items' => $this->items('price_unmapped', 'prod_unmapped', 'si_unmapped'),
        ]));

        $this->postJson('/api/v1/webhooks/stripe', [], ['Stripe-Signature' => 't=test,v1=signature'])
            ->assertOk();

        $this->assertDatabaseMissing('subscriptions', [
            'stripe_subscription_id' => 'sub_unmapped',
        ], 'mysql_central');
        $this->assertDatabaseHas('cashier_subscriptions', [
            'stripe_id' => 'sub_unmapped',
        ], 'mysql_central');
    }

    public function test_cancellation_keeps_the_current_tenant_plan(): void
    {
        $plan = Plan::factory()->create(['stripe_price_id' => 'price_cancel']);
        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
            'stripe_customer_id' => 'cus_cancel',
        ]);

        $this->mockGateway($this->subscriptionEvent('evt_cancel_keep', 'customer.subscription.deleted', [
            'id' => 'sub_cancel_keep',
            'customer' => 'cus_cancel',
            'status' => 'canceled',
            'canceled_at' => 1_762_592_000,
            'items' => $this->items('price_cancel', 'prod_cancel', 'si_cancel_keep'),
        ]));

        $this->postJson('/api/v1/webhooks/stripe', [], ['Stripe-Signature' => 't=test,v1=signature'])
            ->assertOk();

        $this->assertSame('cancelled', Subscription::query()
            ->where('stripe_subscription_id', 'sub_cancel_keep')
            ->value('status'));
        $this->assertSame($plan->id, $tenant->fresh()->plan_id);
    }

    private function mockGateway(array $event): void
    {
        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('verifyWebhookEvent')->once()->andReturn($event);
        $this->app->instance(PaymentGatewayInterface::class, $gateway);
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
            'customer' => 'cus_projection',
            'status' => 'active',
            'metadata' => [],
            'cancel_at_period_end' => false,
            'current_period_start' => 1_760_000_000,
            'current_period_end' => 1_762_592_000,
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
