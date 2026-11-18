<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PRESETS = [
        'global_common' => [
            'name' => 'Global common',
            'codes' => ['USD', 'EUR', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD', 'NZD', 'CNY', 'MXN', 'BRL'],
        ],
        'latin_america' => [
            'name' => 'Latin America',
            'codes' => ['MXN', 'BRL', 'ARS', 'CLP', 'COP', 'PEN', 'UYU'],
        ],
        'europe' => [
            'name' => 'Europe',
            'codes' => ['EUR', 'GBP', 'CHF', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON', 'BGN', 'UAH', 'TRY', 'RUB'],
        ],
        'asia_pacific' => [
            'name' => 'Asia Pacific',
            'codes' => ['JPY', 'CNY', 'HKD', 'SGD', 'INR', 'KRW', 'THB', 'MYR', 'IDR', 'PHP', 'VND', 'AUD', 'NZD'],
        ],
        'all_supported' => [
            'name' => 'All supported currencies',
            'codes' => [],
        ],
    ];

    private const REGIONS = [
        'USD' => 'america', 'CAD' => 'america', 'MXN' => 'america', 'BRL' => 'america',
        'ARS' => 'america', 'CLP' => 'america', 'COP' => 'america', 'PEN' => 'america',
        'UYU' => 'america',
        'EUR' => 'europe', 'GBP' => 'europe', 'CHF' => 'europe', 'SEK' => 'europe',
        'NOK' => 'europe', 'DKK' => 'europe', 'PLN' => 'europe', 'CZK' => 'europe',
        'HUF' => 'europe', 'RON' => 'europe', 'BGN' => 'europe', 'UAH' => 'europe',
        'TRY' => 'europe', 'RUB' => 'europe',
        'JPY' => 'asia_pacific', 'CNY' => 'asia_pacific', 'HKD' => 'asia_pacific',
        'SGD' => 'asia_pacific', 'INR' => 'asia_pacific', 'KRW' => 'asia_pacific',
        'THB' => 'asia_pacific', 'MYR' => 'asia_pacific', 'IDR' => 'asia_pacific',
        'PHP' => 'asia_pacific', 'VND' => 'asia_pacific', 'AUD' => 'asia_pacific',
        'NZD' => 'asia_pacific', 'PKR' => 'asia_pacific', 'BDT' => 'asia_pacific',
        'KWD' => 'middle_east', 'SAR' => 'middle_east', 'AED' => 'middle_east',
        'QAR' => 'middle_east', 'ILS' => 'middle_east',
        'ZAR' => 'africa', 'NGN' => 'africa', 'KES' => 'africa', 'EGP' => 'africa',
        'MAD' => 'africa',
    ];

    private const POPULAR = [
        'USD', 'EUR', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD', 'MXN', 'BRL',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('currencies')) {
            return;
        }

        $this->seedCurrencies();
        $this->seedPresets();
        $this->seedSettings();
        $this->seedPlanPrices();
    }

    public function down(): void
    {
        if (! Schema::hasTable('global_settings')) {
            return;
        }

        DB::table('global_settings')->whereIn('key', $this->settingKeys())->delete();

        if (Schema::hasTable('plan_prices')) {
            DB::table('plan_prices')->where('currency_code', $this->baseCurrency())->delete();
        }

        if (Schema::hasTable('currency_sets')) {
            DB::table('currency_sets')->whereIn('slug', array_keys(self::PRESETS))->delete();
        }

        if (Schema::hasTable('currencies')) {
            DB::table('currencies')->whereIn('code', array_keys($this->currencyDefinitions()))->delete();
        }
    }

    private function seedCurrencies(): void
    {
        $now = now();
        $rows = [];

        foreach ($this->currencyDefinitions() as $code => $definition) {
            $rows[] = [
                'code' => $code,
                'name' => $definition['name'],
                'symbol' => $definition['symbol'],
                'decimal_places' => (int) (config("money.currencies.{$code}.precision") ?? 2),
                'region' => self::REGIONS[$code] ?? 'other',
                'popular' => in_array($code, self::POPULAR, true),
                'active' => true,
                'allow_tenant' => true,
                'allow_public' => true,
                'sort_order' => (int) ($definition['sort_order'] ?? 100),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('currencies')->upsert(
            $rows,
            ['code'],
            [
                'name', 'symbol', 'decimal_places', 'region', 'popular',
                'active', 'allow_tenant', 'allow_public', 'sort_order', 'updated_at',
            ]
        );
    }

    private function seedPresets(): void
    {
        if (! Schema::hasTable('currency_sets')) {
            return;
        }

        $codes = array_keys($this->currencyDefinitions());
        $now = now();
        $rows = [];

        foreach (self::PRESETS as $slug => $preset) {
            $rows[] = [
                'name' => $preset['name'],
                'slug' => $slug,
                'currency_codes' => json_encode(
                    $slug === 'all_supported' ? $codes : array_values(array_intersect($codes, $preset['codes']))
                ),
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('currency_sets')->insertOrIgnore($rows);
    }

    private function seedSettings(): void
    {
        $configured = config('currency.presets.global_common');
        $globalCodes = is_array($configured) ? $configured : self::PRESETS['global_common']['codes'];
        $globalCodes = array_values(array_intersect(array_keys($this->currencyDefinitions()), $globalCodes));

        $settings = [
            'currency' => $this->baseCurrency(),
            'currency_mode' => 'preset',
            'currency_preset' => 'global_common',
            'enabled_currencies' => json_encode($globalCodes),
            'currency_scope' => json_encode(['admin_dashboard', 'public_plans', 'tenant_dashboards']),
            'currency_admin' => $this->baseCurrency(),
            'currency_public' => $this->baseCurrency(),
            'tenant_currency_mode' => 'preset',
            'tenant_currency_preset' => 'global_common',
            'tenant_currencies' => json_encode($globalCodes),
        ];

        $now = now();
        DB::table('global_settings')->insertOrIgnore(
            collect($settings)->map(fn (string $value, string $key) => [
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all()
        );
    }

    private function seedPlanPrices(): void
    {
        if (! Schema::hasTable('plans') || ! Schema::hasTable('plan_prices')) {
            return;
        }

        $base = $this->baseCurrency();
        $now = now();

        DB::table('plans')->orderBy('id')->chunk(100, function ($plans) use ($base, $now) {
            $rows = [];

            foreach ($plans as $plan) {
                $rows[] = [
                    'plan_id' => $plan->id,
                    'currency_code' => $base,
                    'amount' => $plan->price,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('plan_prices')->insertOrIgnore($rows);
        });
    }

    /**
     * @return array<string, array{name: string, symbol: string, sort_order?: int}>
     */
    private function currencyDefinitions(): array
    {
        $definitions = [];
        $sortOrder = 0;

        foreach ((array) config('currency.currencies', []) as $code => $currency) {
            $definitions[$code] = [
                'name' => (string) ($currency['name'] ?? $code),
                'symbol' => (string) ($currency['symbol'] ?? $code),
                'sort_order' => in_array($code, self::POPULAR, true) ? $sortOrder++ : 100,
            ];
        }

        return $definitions;
    }

    /**
     * @return array<int, string>
     */
    private function settingKeys(): array
    {
        return [
            'currency', 'currency_mode', 'currency_preset', 'enabled_currencies',
            'currency_scope', 'currency_admin', 'currency_public',
            'tenant_currency_mode', 'tenant_currency_preset', 'tenant_currencies',
        ];
    }

    private function baseCurrency(): string
    {
        return strtoupper((string) config('currency.base_currency', 'USD'));
    }
};
