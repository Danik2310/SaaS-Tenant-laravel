<?php

declare(strict_types=1);

namespace App\Billing\Contracts;

interface PaymentGatewayInterface
{
    public function charge(float $amount, array $options = []): array;

    public function refund(string $transactionId, float $amount): array;

    public function getCustomer(string $customerId): array;
}
