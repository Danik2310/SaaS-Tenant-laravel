<?php

declare(strict_types=1);

namespace App\Shared\Services;

use Akaunting\Money\Currency as MoneyCurrency;
use Akaunting\Money\Money;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Shared\Contracts\CurrencyCatalogInterface;
use App\Shared\Contracts\CurrencySettingsInterface;
use App\Shared\Contracts\ExchangeRateServiceInterface;
use App\Shared\Contracts\PlanPriceResolverInterface;
use App\Shared\Data\ResolvedPrice;

class PlanPriceResolver implements PlanPriceResolverInterface
{
    public function __construct(
        private ExchangeRateServiceInterface $rates,
        private CurrencyCatalogInterface $catalog,
        private CurrencySettingsInterface $settings,
    ) {}

    public function resolve(Plan $plan, string $currency): ResolvedPrice
    {
        $base = $this->settings->base();
        $code = strtoupper($currency);
        $basePrice = (float) $plan->price;

        $override = $this->overrideFor($plan, $code);
        if ($override !== null) {
            $amount = (float) $override->amount;

            return new ResolvedPrice(
                number_format($amount, 2, '.', ''),
                $code,
                'override',
                $this->format($amount, $code)
            );
        }

        $rate = $code === $base ? 1.0 : ($this->rates->rates()[$code] ?? null);

        if ($rate !== null && $rate > 0) {
            $amount = $this->round($basePrice * (float) $rate, $code);

            return new ResolvedPrice(
                number_format($amount, 2, '.', ''),
                $code,
                'converted',
                $this->format($amount, $code)
            );
        }

        return new ResolvedPrice(
            number_format($basePrice, 2, '.', ''),
            $base,
            'fallback',
            $this->format($basePrice, $base)
        );
    }

    public function display(Plan $plan, string $currency): array
    {
        return $this->resolve($plan, $currency)->toArray();
    }

    private function overrideFor(Plan $plan, string $code): ?PlanPrice
    {
        if ($plan->relationLoaded('prices')) {
            return $plan->prices->firstWhere('currency_code', $code);
        }

        return $plan->prices()->where('currency_code', $code)->first();
    }

    private function round(float $amount, string $code): float
    {
        $decimals = (int) ($this->catalog->find($code)['decimal_places'] ?? 2);

        return round($amount, max(0, min($decimals, 4)));
    }

    private function format(float $amount, string $code): string
    {
        try {
            return (new Money($amount, new MoneyCurrency($code), true))->format();
        } catch (\Throwable) {
            return number_format($amount, 2);
        }
    }
}
