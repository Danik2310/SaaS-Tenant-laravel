<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Billing\Projectors\TenantPaymentCardProjector;
use App\Shared\Contracts\PaymentGatewayInterface;
use PHPUnit\Framework\TestCase;

class TenantPaymentCardProjectorTest extends TestCase
{
    public function test_projection_uses_the_default_card_and_only_masked_fields(): void
    {
        $attributes = $this->projector()->projectedCardAttributes([
            [
                'id' => 'pm_visa',
                'brand' => 'Visa',
                'last4' => '4242',
                'exp_month' => 12,
                'exp_year' => 2029,
                'is_default' => false,
            ],
            [
                'id' => 'pm_mastercard',
                'brand' => 'Mastercard',
                'last4' => '5555',
                'exp_month' => 3,
                'exp_year' => 2030,
                'is_default' => true,
            ],
        ]);

        $this->assertSame([
            'pm_type' => 'Mastercard',
            'pm_last_four' => '5555',
            'pm_exp_month' => 3,
            'pm_exp_year' => 2030,
            'pm_card_count' => 2,
        ], $attributes);
        $this->assertArrayNotHasKey('pan', $attributes);
        $this->assertArrayNotHasKey('cvc', $attributes);
    }

    public function test_projection_clears_the_summary_when_no_cards_remain(): void
    {
        $this->assertSame([
            'pm_type' => null,
            'pm_last_four' => null,
            'pm_exp_month' => null,
            'pm_exp_year' => null,
            'pm_card_count' => 0,
        ], $this->projector()->projectedCardAttributes([]));
    }

    public function test_projection_falls_back_to_the_first_card_without_a_default(): void
    {
        $attributes = $this->projector()->projectedCardAttributes([
            [
                'id' => 'pm_amex',
                'brand' => 'Amex',
                'last4' => '1005',
                'exp_month' => 7,
                'exp_year' => 2028,
                'is_default' => false,
            ],
        ]);

        $this->assertSame('Amex', $attributes['pm_type']);
        $this->assertSame('1005', $attributes['pm_last_four']);
        $this->assertSame(1, $attributes['pm_card_count']);
    }

    public function test_payload_projection_uses_the_attached_card_without_a_gateway_call(): void
    {
        $projector = $this->projector();
        $event = [
            'id' => 'evt_attach',
            'type' => 'payment_method.attached',
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

        $this->assertSame([
            'pm_type' => 'visa',
            'pm_last_four' => '4242',
            'pm_exp_month' => 12,
            'pm_exp_year' => 2030,
            'pm_card_count' => 2,
        ], $projector->projectedAttributesFromEvent($event, 1));
    }

    public function test_payload_projection_clears_the_summary_on_detach(): void
    {
        $this->assertSame([
            'pm_type' => null,
            'pm_last_four' => null,
            'pm_exp_month' => null,
            'pm_exp_year' => null,
            'pm_card_count' => 0,
        ], $this->projector()->projectedAttributesFromEvent([
            'id' => 'evt_detach',
            'type' => 'payment_method.detached',
            'object' => ['id' => 'pm_test', 'customer' => 'cus_webhook', 'card' => []],
            'previous' => [],
        ]));
    }

    public function test_only_card_events_are_handled_and_default_changes_are_detected(): void
    {
        $projector = $this->projector();

        $this->assertTrue($projector->handles('payment_method.attached'));
        $this->assertTrue($projector->handles('payment_method.detached'));
        $this->assertTrue($projector->handles('customer.updated'));
        $this->assertFalse($projector->handles('customer.subscription.updated'));

        $event = [
            'id' => 'evt_1',
            'type' => 'customer.updated',
            'object' => [
                'id' => 'cus_1',
                'invoice_settings' => ['default_payment_method' => 'pm_new'],
            ],
            'previous' => [
                'id' => 'cus_1',
                'invoice_settings' => ['default_payment_method' => 'pm_old'],
            ],
        ];

        $this->assertTrue($projector->isCardChange($event));
        $this->assertSame('cus_1', $projector->customerIdFromEvent($event));

        $event['previous']['invoice_settings']['default_payment_method'] = 'pm_new';
        $this->assertFalse($projector->isCardChange($event));
    }

    private function projector(): TenantPaymentCardProjector
    {
        return new TenantPaymentCardProjector(
            $this->createMock(PaymentGatewayInterface::class),
        );
    }
}
