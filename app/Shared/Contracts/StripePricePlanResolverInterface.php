<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use App\Models\Plan;

interface StripePricePlanResolverInterface
{
    /**
     * Resolve the local plan entitled by a Stripe recurring price id.
     */
    public function resolve(string $stripePriceId): ?Plan;
}
