<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use Brick\Money\Money;

interface ExchangeRateServiceInterface
{
    /**
     * Supported currencies as [code => ['name' => string, 'symbol' => string]].
     */
    public function currencies(): array;

    /**
     * Cached USD -> currency rates, always including the base currency at 1.0.
     */
    public function rates(): array;

    /**
     * Whether the cached rates were fetched from the live API.
     */
    public function isLive(): bool;

    /**
     * ISO-8601 timestamp of the last successful API fetch, null when fallback.
     */
    public function updatedAt(): ?string;

    /**
     * Convert a USD amount into the given currency using brick/money.
     */
    public function convert(float $amountUsd, string $currencyCode): Money;

    /**
     * Admin display currency from the global settings, validated against the
     * supported list and falling back to the base currency.
     */
    public function displayCurrency(): string;
}
