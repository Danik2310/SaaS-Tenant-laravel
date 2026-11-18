<?php

declare(strict_types=1);

namespace App\Shared\Services;

use Akaunting\Money\Currency as MoneyCurrency;
use Akaunting\Money\Money;
use App\Shared\Contracts\CurrencyCatalogInterface;
use App\Shared\Contracts\CurrencySettingsInterface;
use App\Shared\Contracts\ExchangeRateServiceInterface;
use Illuminate\Support\Facades\Cache;
use Swap\Swap;
use Throwable;

class ExchangeRateService implements ExchangeRateServiceInterface
{
    public function __construct(
        private Swap $swap,
        private CurrencyCatalogInterface $catalog,
        private CurrencySettingsInterface $settings,
    ) {}

    public function currencies(): array
    {
        return $this->catalog->active();
    }

    public function rates(): array
    {
        return $this->snapshot()['rates'];
    }

    public function isLive(): bool
    {
        return (bool) $this->snapshot()['is_live'];
    }

    public function updatedAt(): ?string
    {
        return $this->snapshot()['updated_at'];
    }

    public function convert(float $amountBase, string $currencyCode): Money
    {
        $base = $this->settings->base();
        $code = strtoupper($currencyCode);
        $catalog = $this->catalog->active();

        if (! isset($catalog[$code])) {
            $code = $base;
        }

        $rate = $code === $base ? 1.0 : ($this->rates()[$code] ?? 1.0);
        $currency = new MoneyCurrency($code);

        return new Money($amountBase * $rate, $currency, true);
    }

    public function displayCurrency(): string
    {
        return $this->settings->adminCurrency();
    }

    public function forgetCache(): void
    {
        Cache::forget((string) config('currency.cache_key'));
    }

    private function snapshot(): array
    {
        return Cache::remember(
            (string) config('currency.cache_key'),
            (int) config('currency.cache_ttl', 86400),
            fn (): array => $this->fetch()
        );
    }

    private function fetch(): array
    {
        $base = $this->settings->base();
        $rates = [$base => 1.0];
        $updatedAt = null;
        $successes = 0;

        foreach (array_keys($this->catalog->active()) as $code) {
            if ($code === $base) {
                continue;
            }

            try {
                $rate = $this->swap->latest("{$base}/{$code}");
                $value = $rate->getValue();

                if ($value > 0) {
                    $rates[$code] = (float) $value;
                    $successes++;
                    $updatedAt ??= $rate->getDate()->format('c');
                }
            } catch (Throwable) {
                // Fall back to the static table below.
            }
        }

        if ($successes === 0) {
            return [
                'rates' => $this->fallbackRates($base),
                'updated_at' => null,
                'is_live' => false,
            ];
        }

        return [
            'rates' => $rates,
            'updated_at' => $updatedAt,
            'is_live' => true,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function fallbackRates(string $base): array
    {
        $fallback = (array) config('currency.fallback_rates', []);
        $rates = [];

        foreach (array_keys($this->catalog->active()) as $code) {
            if (isset($fallback[$code]) && is_numeric($fallback[$code])) {
                $rates[$code] = (float) $fallback[$code];
            }
        }

        $rates[$base] = 1.0;

        return $rates;
    }
}
