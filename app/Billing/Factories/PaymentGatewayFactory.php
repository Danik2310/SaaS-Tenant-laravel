<?php

declare(strict_types=1);

namespace App\Billing\Factories;

use App\Billing\Adapters\MercadoPagoAdapter;
use App\Billing\Adapters\StripePaymentAdapter;
use App\Billing\Contracts\PaymentGatewayInterface;
use Stripe\StripeClient;

class PaymentGatewayFactory
{
    public static function make(?string $gateway = null): PaymentGatewayInterface
    {
        return match ($gateway) {
            'stripe' => self::stripeAdapter(),
            'mercadopago' => new MercadoPagoAdapter,
            default => self::stripeAdapter(),
        };
    }

    public static function fromTenant(?object $tenant = null): PaymentGatewayInterface
    {
        $gateway = $tenant->payment_gateway ?? config('billing.default_gateway', 'stripe');

        return self::make($gateway);
    }

    private static function stripeAdapter(): StripePaymentAdapter
    {
        return new StripePaymentAdapter(
            new StripeClient((string) config('services.stripe.secret_key')),
        );
    }
}
