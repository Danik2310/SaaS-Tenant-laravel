<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface CurrencySettingsInterface
{
    public function base(): string;

    public function mode(): string;

    public function preset(): string;

    /**
     * @return array<int, string>
     */
    public function enabledCurrencies(): array;

    /**
     * @return array<int, string>
     */
    public function scopes(): array;

    public function hasScope(string $scope): bool;

    public function adminCurrency(): string;

    public function publicCurrency(): string;

    /**
     * @return array<int, string>
     */
    public function tenantCurrencies(): array;

    public function tenantCurrency(): string;

    public function tenantMode(): string;

    public function tenantPreset(): string;

    public function forSurface(string $surface): string;
}
