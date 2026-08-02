<?php

declare(strict_types=1);

namespace App\Shared\Services;

use App\Models\GlobalSetting;
use App\Shared\Contracts\ExchangeRateServiceInterface;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Brick\Money\Currency;
use Brick\Money\CurrencyConverter;
use Brick\Money\ExchangeRateProvider\ConfigurableProvider;
use Brick\Money\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ExchangeRateService implements ExchangeRateServiceInterface
{
    public function currencies(): array
    {
        return config('currency.currencies');
    }

    public function rates(): array
    {
        return $this->snapshot()['rates'];
    }

    public function isLive(): bool
    {
        return $this->snapshot()['is_live'];
    }

    public function updatedAt(): ?string
    {
        return $this->snapshot()['updated_at'];
    }

    public function convert(float $amountUsd, string $currencyCode): Money
    {
        $currency = Currency::of(strtoupper($currencyCode));

        $provider = new ConfigurableProvider;
        $provider->setExchangeRate($this->base(), $currency->getCurrencyCode(), BigRational::of('1'));

        foreach ($this->rates() as $code => $rate) {
            $provider->setExchangeRate($this->base(), $code, BigRational::of((string) $rate));
        }

        $converter = new CurrencyConverter($provider);

        return $converter->convert(
            Money::of((string) $amountUsd, $this->base()),
            $currency,
            roundingMode: RoundingMode::HalfUp
        );
    }

    public function displayCurrency(): string
    {
        $code = strtoupper((string) GlobalSetting::get('currency', $this->base()));

        return array_key_exists($code, $this->currencies()) ? $code : $this->base();
    }

    private function base(): string
    {
        return config('currency.base_currency');
    }

    private function snapshot(): array
    {
        return Cache::remember(
            config('currency.cache_key'),
            config('currency.cache_ttl'),
            fn () => $this->fetch()
        );
    }

    private function fetch(): array
    {
        try {
            $response = Http::timeout(config('currency.api_timeout'))
                ->get(config('currency.api_url'));

            $data = $response->json();

            if ($response->ok()
                && ($data['result'] ?? '') === 'success'
                && ($data['base_code'] ?? null) === $this->base()
                && is_array($data['rates'] ?? null)) {
                $rates = array_intersect_key($data['rates'], $this->currencies());
                $rates = array_filter($rates, fn ($rate) => is_numeric($rate) && (float) $rate > 0);

                if ($rates !== []) {
                    return [
                        'rates' => $this->normalizeRates($rates),
                        'updated_at' => isset($data['time_last_update_unix'])
                            ? date('c', (int) $data['time_last_update_unix'])
                            : now()->toIso8601String(),
                        'is_live' => true,
                    ];
                }
            }
        } catch (Throwable) {
            // Fall through to the offline fallback table.
        }

        return [
            'rates' => config('currency.fallback_rates'),
            'updated_at' => null,
            'is_live' => false,
        ];
    }

    private function normalizeRates(array $rates): array
    {
        $normalized = [];

        foreach ($rates as $code => $rate) {
            $normalized[$code] = (float) $rate;
        }

        $normalized[$this->base()] = 1.0;

        return $normalized;
    }
}
