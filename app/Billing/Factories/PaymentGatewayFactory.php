<?php

declare(strict_types=1);

namespace App\Billing\Factories;

use App\Billing\Adapters\MercadoPagoAdapter;
use App\Billing\Adapters\StripePaymentAdapter;
use App\Billing\Contracts\PaymentGatewayInterface;

class PaymentGatewayFactory
{
    public static function make(?string $gateway = null): PaymentGatewayInterface
    {
        return match ($gateway) {
            'stripe' => new StripePaymentAdapter,
            'mercadopago' => new MercadoPagoAdapter,
            default => new StripePaymentAdapter,
        };
    }

    public static function fromTenant(?object $tenant = null): PaymentGatewayInterface
    {
        $gateway = $tenant->payment_gateway ?? config('billing.default_gateway', 'stripe');

        return self::make($gateway);
    }
}
