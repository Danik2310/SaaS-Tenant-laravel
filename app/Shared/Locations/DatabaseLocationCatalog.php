<?php

declare(strict_types=1);

namespace App\Shared\Locations;

use App\Shared\Contracts\LocationCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Reference location data backed by the altwaireb/laravel-world tables
 * (countries / states / cities) on the default connection.
 */
class DatabaseLocationCatalog implements LocationCatalog
{
    public function countries(): array
    {
        return Cache::remember('world.countries', 86400, function () {
            return DB::table('countries')
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['iso2', 'name'])
                ->map(fn ($row) => ['code' => $row->iso2, 'name' => $row->name])
                ->values()
                ->all();
        });
    }

    public function statesByCountry(): array
    {
        return Cache::remember('world.states.all', 86400, function () {
            return DB::table('states')
                ->join('countries', 'countries.id', '=', 'states.country_id')
                ->where('states.is_active', 1)
                ->whereNull('states.deleted_at')
                ->where('countries.is_active', 1)
                ->whereNull('countries.deleted_at')
                ->orderBy('countries.iso2')
                ->orderBy('states.name')
                ->get(['countries.iso2', 'states.name'])
                ->groupBy('iso2')
                ->map(fn ($group) => $group->pluck('name')->all())
                ->all();
        });
    }

    public function cityNames(string $countryCode, ?string $stateName, string $term, int $limit): array
    {
        $countryId = $this->countryIdByCode($countryCode);

        if ($countryId === null) {
            return [];
        }

        $query = DB::table('cities')
            ->where('country_id', $countryId)
            ->where('is_active', 1)
            ->whereNull('deleted_at');

        if ($stateName !== null && $stateName !== '') {
            $stateId = $this->stateIdInCountry($countryId, $stateName);

            if ($stateId === null) {
                return [];
            }

            $query->where('state_id', $stateId);
        }

        if ($term !== '') {
            $query->where('name', 'like', '%'.$term.'%');
        }

        return $query
            ->orderByRaw('name = ? desc', [$term])
            ->orderByRaw('name like ? desc', [$term.'%'])
            ->orderBy('name')
            ->limit(max(1, min(100, $limit)))
            ->pluck('name')
            ->all();
    }

    public function countryExistsByName(string $countryName): bool
    {
        return $this->countryRowByName($countryName) !== null;
    }

    public function countryCodeByName(string $countryName): ?string
    {
        return $this->countryRowByName($countryName)?->iso2;
    }

    public function stateBelongsToCountry(string $countryName, string $stateName): bool
    {
        $country = $this->countryRowByName($countryName);

        return $country !== null
            && $this->stateIdInCountry((int) $country->id, $stateName) !== null;
    }

    public function cityMatches(string $countryName, ?string $stateName, string $cityName): bool
    {
        $country = $this->countryRowByName($countryName);

        if ($country === null) {
            return false;
        }

        $query = DB::table('cities')
            ->where('country_id', $country->id)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->where('name', $cityName);

        if ($stateName !== null && $stateName !== '') {
            $stateId = $this->stateIdInCountry((int) $country->id, $stateName);

            if ($stateId === null) {
                return false;
            }

            $query->where('state_id', $stateId);
        }

        return $query->exists();
    }

    private function countryIdByCode(string $countryCode): ?int
    {
        $id = DB::table('countries')
            ->where('iso2', strtoupper($countryCode))
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * @return object|null
     */
    private function countryRowByName(string $countryName)
    {
        return DB::table('countries')
            ->where('name', $countryName)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->first(['id', 'iso2']);
    }

    private function stateIdInCountry(int $countryId, string $stateName): ?int
    {
        $id = DB::table('states')
            ->where('country_id', $countryId)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->where('name', $stateName)
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }
}
