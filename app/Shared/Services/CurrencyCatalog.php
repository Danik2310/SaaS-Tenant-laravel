<?php

declare(strict_types=1);

namespace App\Shared\Services;

use App\Models\Currency;
use App\Models\CurrencySet;
use App\Shared\Contracts\CurrencyCatalogInterface;
use Illuminate\Support\Str;

class CurrencyCatalog implements CurrencyCatalogInterface
{
    public function all(): array
    {
        return Currency::query()
            ->whereIn('code', $this->supportedCodes())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->keyBy('code')
            ->map(fn (Currency $currency): array => $this->serialize($currency))
            ->all();
    }

    public function active(): array
    {
        return array_filter($this->all(), fn (array $currency): bool => $currency['active']);
    }

    public function find(string $code): ?array
    {
        $currency = Currency::query()
            ->whereIn('code', $this->supportedCodes())
            ->where('code', strtoupper($code))
            ->first();

        return $currency ? $this->serialize($currency) : null;
    }

    public function presets(): array
    {
        $presets = CurrencySet::query()->orderBy('name')->get()->mapWithKeys(function (CurrencySet $set) {
            return [$set->slug => [
                'name' => $set->name,
                'currencies' => array_values((array) $set->currency_codes),
            ]];
        });

        return [
            'custom' => ['name' => 'Custom', 'currencies' => []],
            ...$presets->all(),
        ];
    }

    public function codesForPreset(string $preset): array
    {
        $preset = strtolower(trim($preset));
        if ($preset === '' || $preset === 'custom') {
            return [];
        }

        $set = CurrencySet::query()->where('slug', $preset)->first();

        return array_values((array) ($set?->currency_codes ?? []));
    }

    public function resolveCodes(array $codes, ?string $scope = null): array
    {
        $catalog = $this->all();
        $resolved = [];

        foreach ($codes as $code) {
            $code = strtoupper(trim((string) $code));
            $currency = $catalog[$code] ?? null;

            if (! $currency || ! $currency['active']) {
                continue;
            }

            if ($scope === 'tenant' && ! $currency['allow_tenant']) {
                continue;
            }

            if ($scope === 'public' && ! $currency['allow_public']) {
                continue;
            }

            $resolved[] = $code;
        }

        return array_values(array_unique($resolved));
    }

    public function search(?string $query = null, ?string $scope = null, int $limit = 100): array
    {
        $needle = Str::lower(trim((string) $query));
        $currencies = $scope === null ? $this->all() : $this->active();

        $matches = array_values(array_filter(
            $currencies,
            function (array $currency) use ($needle, $scope): bool {
                if ($needle !== ''
                    && ! str_contains(Str::lower($currency['code']), $needle)
                    && ! str_contains(Str::lower($currency['name']), $needle)) {
                    return false;
                }

                return match ($scope) {
                    'tenant' => $currency['active'] && $currency['allow_tenant'],
                    'public' => $currency['active'] && $currency['allow_public'],
                    default => true,
                };
            }
        ));

        return array_slice($matches, 0, max(1, min($limit, 500)));
    }

    /**
     * @return array<int, string>
     */
    private function supportedCodes(): array
    {
        return array_keys((array) config('currency.currencies', []));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Currency $currency): array
    {
        return [
            'code' => $currency->code,
            'name' => $currency->name,
            'symbol' => $currency->symbol,
            'decimal_places' => $currency->decimal_places,
            'region' => $currency->region,
            'popular' => $currency->popular,
            'active' => $currency->active,
            'allow_tenant' => $currency->allow_tenant,
            'allow_public' => $currency->allow_public,
            'sort_order' => $currency->sort_order,
        ];
    }
}
