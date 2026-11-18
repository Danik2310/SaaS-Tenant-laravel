<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use Akaunting\Money\Money;

interface ExchangeRateServiceInterface
{
    /**
     * Currency catalog as [code => ['name' => string, 'symbol' => string, ...]].
     *
     * @return array<string, array<string, mixed>>
     */
    public function currencies(): array;

    /**
     * Cached base-currency -> currency rates, always including the base at 1.0.
     *
     * @return array<string, float>
     */
    public function rates(): array;

    public function isLive(): bool;

    public function updatedAt(): ?string;

    /**
     * Convert a base-currency amount into the given currency using Akaunting Money.
     */
    public function convert(float $amountBase, string $currencyCode): Money;

    /**
     * Admin display currency from the global settings, validated against the
     * currency catalog and falling back to the base currency.
     */
    public function displayCurrency(): string;

    /**
     * Forget the cached exchange-rate snapshot.
     */
    public function forgetCache(): void;
}
