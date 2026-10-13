<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * Reference data behind the registration form's country/state/city cascade.
 *
 * The Tenants module must not import vendor models directly, so all location
 * lookups happen through this contract.
 */
interface LocationCatalog
{
    /**
     * @return array<int, array{code: string, name: string}>
     */
    public function countries(): array;

    /**
     * Every state name grouped by its country's iso2 code, for building the
     * registration form's country/state cascade in one query.
     *
     * @return array<string, array<int, string>>
     */
    public function statesByCountry(): array;

    /**
     * Every active city name in a country, narrowed to a single state when one
     * is given. Ordered alphabetically so the registration form can render the
     * whole scope as a dropdown.
     *
     * @return array<int, string>
     */
    public function cityNamesForScope(string $countryCode, ?string $stateName): array;

    /**
     * Whether the submitted country name matches a known, active country.
     */
    public function countryExistsByName(string $countryName): bool;

    /**
     * Resolve a submitted country name to its iso2 code, or null when unknown.
     */
    public function countryCodeByName(string $countryName): ?string;

    /**
     * Whether the submitted state name belongs to the submitted country.
     */
    public function stateBelongsToCountry(string $countryName, string $stateName): bool;

    /**
     * Whether the submitted city exists in the country — inside the state when
     * one is given, country-wide otherwise.
     */
    public function cityMatches(string $countryName, ?string $stateName, string $cityName): bool;
}
