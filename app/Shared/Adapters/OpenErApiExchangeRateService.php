<?php

declare(strict_types=1);

namespace App\Shared\Adapters;

use Carbon\CarbonImmutable;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;
use Exchanger\Contract\ExchangeRateProvider;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\ExchangeRateService as ExchangeRateServiceContract;
use Exchanger\CurrencyPair;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\ExchangeRate;
use Exchanger\HistoricalExchangeRateQuery;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Swap provider backed by the free open.er-api.com endpoint.
 *
 * The endpoint returns every USD-based rate in one response, so the adapter
 * memoises the payload per request instead of issuing one HTTP call per pair.
 */
class OpenErApiExchangeRateService implements ExchangeRateProvider, ExchangeRateServiceContract
{
    /** @var array<string, float>|null */
    private ?array $rates = null;

    private ?CarbonImmutable $updatedAt = null;

    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return ! $exchangeQuery instanceof HistoricalExchangeRateQuery;
    }

    public function getName(): string
    {
        return 'open_er_api';
    }

    public function getExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $pair = $exchangeQuery->getCurrencyPair();

        if ($pair->isIdentical()) {
            return new ExchangeRate($pair, 1.0, $this->timestamp(), $this->getName());
        }

        $rates = $this->rates();
        $base = strtoupper($pair->getBaseCurrency());
        $quote = strtoupper($pair->getQuoteCurrency());
        $usd = strtoupper((string) config('currency.base_currency', 'USD'));

        $rate = match (true) {
            $base === $usd => $rates[$quote] ?? null,
            $quote === $usd => isset($rates[$base]) && $rates[$base] > 0 ? 1 / $rates[$base] : null,
            default => isset($rates[$base], $rates[$quote]) && $rates[$base] > 0
                ? $rates[$quote] / $rates[$base]
                : null,
        };

        if ($rate === null || $rate <= 0) {
            throw new UnsupportedCurrencyPairException($pair, $this);
        }

        return new ExchangeRate($pair, (float) $rate, $this->timestamp(), $this->getName());
    }

    public function updatedAt(): ?CarbonImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return array<string, float>
     */
    private function rates(): array
    {
        if ($this->rates !== null) {
            return $this->rates;
        }

        try {
            $response = Http::timeout((int) config('currency.api_timeout', 5))
                ->get((string) config('currency.api_url'));
            $data = $response->json();
        } catch (Throwable) {
            $data = null;
        }

        if (! is_array($data)
            || ($data['result'] ?? null) !== 'success'
            || strtoupper((string) ($data['base_code'] ?? '')) !== strtoupper((string) config('currency.base_currency', 'USD'))
            || ! is_array($data['rates'] ?? null)) {
            throw new UnsupportedCurrencyPairException(
                CurrencyPair::createFromString('USD/USD'),
                $this
            );
        }

        $rates = [];
        foreach ($data['rates'] as $code => $rate) {
            if (is_numeric($rate) && (float) $rate > 0) {
                $rates[strtoupper((string) $code)] = (float) $rate;
            }
        }

        if ($rates === []) {
            throw new UnsupportedCurrencyPairException(
                CurrencyPair::createFromString('USD/USD'),
                $this
            );
        }

        $this->rates = $rates;
        $this->updatedAt = isset($data['time_last_update_unix'])
            ? CarbonImmutable::createFromTimestamp((int) $data['time_last_update_unix'])
            : CarbonImmutable::now();

        return $this->rates;
    }

    private function timestamp(): CarbonImmutable
    {
        return $this->updatedAt ?? CarbonImmutable::now();
    }
}
