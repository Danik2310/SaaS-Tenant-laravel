<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface CurrencyCatalogInterface
{
    /**
     * Every catalog entry keyed by ISO-4217 code, including inactive rows.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array;

    /**
     * Active catalog entries keyed by code.
     *
     * @return array<string, array<string, mixed>>
     */
    public function active(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $code): ?array;

    /**
     * @return array<string, array{name: string, currencies: array<int, string>}>
     */
    public function presets(): array;

    /**
     * @return array<int, string>
     */
    public function codesForPreset(string $preset): array;

    /**
     * Filter codes to existing catalog rows. A scope of `tenant` or `public`
     * additionally enforces the corresponding allow flag.
     *
     * @param  array<int, string>  $codes
     * @return array<int, string>
     */
    public function resolveCodes(array $codes, ?string $scope = null): array;

    /**
     * Searchable list for admin selectors.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(?string $query = null, ?string $scope = null, int $limit = 100): array;
}
