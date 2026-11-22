<?php

declare(strict_types=1);

namespace App\Billing\Resolvers;

use App\Models\Plan;
use App\Shared\Contracts\StripePricePlanResolverInterface;

class StripePricePlanResolver implements StripePricePlanResolverInterface
{
    public function resolve(string $stripePriceId): ?Plan
    {
        if ($stripePriceId === '') {
            return null;
        }

        return Plan::query()
            ->where('stripe_price_id', $stripePriceId)
            ->first();
    }
}
