<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Billing\Adapters\MercadoPagoAdapter;
use App\Shared\Contracts\PaymentGatewayInterface;
use Tests\TestCase;

class MercadoPagoAdapterTest extends TestCase
{
    public function test_implements_shared_payment_gateway_interface(): void
    {
        $adapter = new MercadoPagoAdapter;
        $this->assertInstanceOf(PaymentGatewayInterface::class, $adapter);
    }

    public function test_charge_returns_empty_array(): void
    {
        $this->assertSame([], (new MercadoPagoAdapter)->charge(10.00));
    }

    public function test_refund_returns_empty_array(): void
    {
        $this->assertSame([], (new MercadoPagoAdapter)->refund('tx_123', 10.00));
    }

    public function test_get_customer_returns_empty_array(): void
    {
        $this->assertSame([], (new MercadoPagoAdapter)->getCustomer('cus_123'));
    }

    public function test_create_checkout_session_throws_when_not_implemented(): void
    {
        $this->expectException(\BadMethodCallException::class);
        (new MercadoPagoAdapter)->createCheckoutSession([
            'amount' => 10.00,
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
        ]);
    }

    public function test_retrieve_checkout_session_throws_when_not_implemented(): void
    {
        $this->expectException(\BadMethodCallException::class);
        (new MercadoPagoAdapter)->retrieveCheckoutSession('sess_123');
    }
}
