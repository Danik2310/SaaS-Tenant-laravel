<?php

namespace Tests\Unit\Billing;

use App\Billing\Adapters\MercadoPagoAdapter;
use App\Billing\Adapters\StripePaymentAdapter;
use App\Billing\Contracts\PaymentGatewayInterface;
use App\Billing\Factories\PaymentGatewayFactory;
use Tests\TestCase;

class PaymentGatewayFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.stripe.secret_key', 'sk_test_factory_key');
    }

    public function test_make_returns_stripe_adapter_by_default(): void
    {
        $this->assertInstanceOf(StripePaymentAdapter::class, PaymentGatewayFactory::make());
    }

    public function test_make_stripe_returns_stripe_adapter(): void
    {
        $this->assertInstanceOf(StripePaymentAdapter::class, PaymentGatewayFactory::make('stripe'));
    }

    public function test_make_mercadopago_returns_mercadopago_adapter(): void
    {
        $this->assertInstanceOf(MercadoPagoAdapter::class, PaymentGatewayFactory::make('mercadopago'));
    }

    public function test_adapter_implements_gateway_interface(): void
    {
        $this->assertInstanceOf(PaymentGatewayInterface::class, PaymentGatewayFactory::make());
    }

    public function test_from_tenant_uses_configured_default_gateway(): void
    {
        config()->set('billing.default_gateway', 'stripe');

        $this->assertInstanceOf(StripePaymentAdapter::class, PaymentGatewayFactory::fromTenant());
    }

    public function test_from_tenant_uses_tenant_gateway_when_set(): void
    {
        $tenant = (object) ['payment_gateway' => 'mercadopago'];

        $this->assertInstanceOf(MercadoPagoAdapter::class, PaymentGatewayFactory::fromTenant($tenant));
    }
}
