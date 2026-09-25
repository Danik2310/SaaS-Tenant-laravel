<?php

declare(strict_types=1);

namespace App\Billing\Adapters;

use App\Billing\Contracts\PaymentGatewayInterface;
use BadMethodCallException;

class MercadoPagoAdapter implements PaymentGatewayInterface
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

    public function createCheckoutSession(array $params): array
    {
        throw new BadMethodCallException('MercadoPago hosted checkout is not implemented.');
    }

    public function retrieveCheckoutSession(string $sessionId): array
    {
        throw new BadMethodCallException('MercadoPago hosted checkout is not implemented.');
    }
}
