<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Billing\Notifications\PaymentMethodChanged;
use App\Models\StripeWebhookEvent;
use App\Models\Tenant;
use App\Shared\Contracts\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_card_attached_event_sends_one_confirmation_per_recipient(): void
    {
        $tenant = Tenant::factory()->create([
            'stripe_customer_id' => 'cus_webhook',
            'billing_email' => 'billing@example.com',
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('verifyWebhookEvent')
            ->once()
            ->andReturn($this->event('evt_card_added', 'payment_method.attached'));
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        $this->postJson('/api/v1/webhooks/stripe', [], ['Stripe-Signature' => 't=test,v1=signature'])
            ->assertOk()
            ->assertJson(['received' => true]);

        Notification::assertSentOnDemandTimes(PaymentMethodChanged::class, 1);
        Notification::assertSentOnDemand(
            PaymentMethodChanged::class,
            function (PaymentMethodChanged $notification, array $channels, $notifiable) use ($tenant): bool {
                $payload = $notification->toArray(null);
                $recipients = $notifiable->routes['mail'] ?? [];

                return $payload['tenant_id'] === $tenant->id
                    && $payload['action'] === 'added'
                    && $payload['card']['last4'] === '4242'
                    && in_array('billing@example.com', $recipients, true)
                    && in_array($tenant->email, $recipients, true);
            },
        );

        $this->assertDatabaseHas('stripe_webhook_events', [
            'id' => 'evt_card_added',
            'type' => 'payment_method.attached',
        ], 'mysql_central');
    }

    public function test_retried_event_does_not_send_a_duplicate_confirmation(): void
    {
        Tenant::factory()->create(['stripe_customer_id' => 'cus_webhook']);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('verifyWebhookEvent')
            ->twice()
            ->andReturn($this->event('evt_retry', 'payment_method.attached'));
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        $headers = ['Stripe-Signature' => 't=test,v1=signature'];

        $this->postJson('/api/v1/webhooks/stripe', [], $headers)->assertOk();
        $this->postJson('/api/v1/webhooks/stripe', [], $headers)
            ->assertOk()
            ->assertJson(['duplicate' => true]);

        Notification::assertSentOnDemandTimes(PaymentMethodChanged::class, 1);
        $this->assertSame(1, StripeWebhookEvent::query()->where('id', 'evt_retry')->count());
    }

    public function test_invalid_signature_is_rejected_without_recording_the_event(): void
    {
        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('verifyWebhookEvent')
            ->once()
            ->andThrow(new InvalidArgumentException('bad signature'));
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        $this->postJson('/api/v1/webhooks/stripe', [], ['Stripe-Signature' => 'bad'])
            ->assertStatus(400)
            ->assertJson(['message' => 'Invalid webhook signature.']);

        Notification::assertNothingSent();
        $this->assertSame(0, StripeWebhookEvent::query()->count());
    }

    /**
     * @return array{id: string, type: string, object: array<string, mixed>, previous: array<string, mixed>}
     */
    private function event(string $id, string $type): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'object' => [
                'id' => 'pm_test',
                'customer' => 'cus_webhook',
                'card' => [
                    'brand' => 'visa',
                    'last4' => '4242',
                    'exp_month' => 12,
                    'exp_year' => 2030,
                ],
            ],
            'previous' => [],
        ];
    }
}
