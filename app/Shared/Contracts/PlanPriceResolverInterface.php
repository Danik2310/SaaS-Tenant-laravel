<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use App\Models\Plan;
use App\Shared\Data\ResolvedPrice;

interface PlanPriceResolverInterface
{
    public function resolve(Plan $plan, string $currency): ResolvedPrice;

    /**
     * @return array{amount: string, currency: string, source: string, formatted: string}
     */
    public function display(Plan $plan, string $currency): array;
}
