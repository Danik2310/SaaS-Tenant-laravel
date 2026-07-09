<?php

declare(strict_types=1);

namespace App\Billing\Adapters;

use App\Billing\Contracts\PaymentGatewayInterface;

class StripePaymentAdapter implements PaymentGatewayInterface
{
    public function charge(float $amount, array $options = []): array
    {
        return [];
    }

    public function refund(string $transactionId, float $amount): array
    {
        return [];
    }

    public function getCustomer(string $customerId): array
    {
        return [];
    }
}
