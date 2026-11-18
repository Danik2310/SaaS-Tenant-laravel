<?php

declare(strict_types=1);

namespace App\Shared\Services;

use App\Models\GlobalSetting;
use App\Shared\Contracts\CurrencyCatalogInterface;
use App\Shared\Contracts\CurrencySettingsInterface;
use App\Shared\Contracts\TenantCurrencyResolverInterface;

class CurrencySettings implements CurrencySettingsInterface
{
    private const DEFAULT_SCOPES = ['admin_dashboard', 'public_plans', 'tenant_dashboards'];

    public function __construct(
        private CurrencyCatalogInterface $catalog,
        private TenantCurrencyResolverInterface $tenantCurrency,
    ) {}

    public function base(): string
    {
        return strtoupper((string) config('currency.base_currency', 'USD'));
    }

    public function mode(): string
    {
        $mode = strtolower((string) GlobalSetting::get('currency_mode', 'preset'));

        return in_array($mode, ['preset', 'custom'], true) ? $mode : 'preset';
    }

    public function preset(): string
    {
        $preset = strtolower((string) GlobalSetting::get('currency_preset', 'global_common'));

        return array_key_exists($preset, $this->catalog->presets()) ? $preset : 'global_common';
    }

    public function enabledCurrencies(): array
    {
        $codes = $this->mode() === 'custom'
            ? $this->decode(GlobalSetting::get('enabled_currencies', '[]'))
            : $this->catalog->codesForPreset($this->preset());

        $resolved = $this->catalog->resolveCodes($codes);

        return $resolved === [] ? [$this->base()] : $resolved;
    }

    public function scopes(): array
    {
        $scopes = $this->decode(GlobalSetting::get('currency_scope', null));

        if ($scopes === []) {
            return self::DEFAULT_SCOPES;
        }

        return array_values(array_intersect(self::DEFAULT_SCOPES, array_map('strtolower', $scopes)));
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes(), true);
    }

    public function adminCurrency(): string
    {
        $enabled = $this->enabledCurrencies();
        $code = strtoupper((string) (
            GlobalSetting::get('currency_admin')
            ?: GlobalSetting::get('currency', $this->base())
        ));

        return $this->pick($code, $enabled);
    }

    public function publicCurrency(): string
    {
        $enabled = $this->hasScope('public_plans') ? $this->enabledCurrencies() : [$this->base()];
        $code = strtoupper((string) (GlobalSetting::get('currency_public') ?: $this->adminCurrency()));

        return $this->pick($code, $enabled, 'public');
    }

    public function tenantCurrencies(): array
    {
        $codes = $this->tenantMode() === 'custom'
            ? $this->decode(GlobalSetting::get('tenant_currencies', '[]'))
            : $this->catalog->codesForPreset($this->tenantPreset());

        $resolved = $this->catalog->resolveCodes($codes, 'tenant');

        return $resolved === [] ? [$this->base()] : $resolved;
    }

    public function tenantCurrency(): string
    {
        $code = $this->tenantCurrency->currentCode();

        return $code === null
            ? $this->base()
            : $this->pick($code, $this->tenantCurrencies(), 'tenant');
    }

    public function tenantMode(): string
    {
        $mode = strtolower((string) GlobalSetting::get('tenant_currency_mode', 'preset'));

        return in_array($mode, ['preset', 'custom'], true) ? $mode : 'preset';
    }

    public function tenantPreset(): string
    {
        $preset = strtolower((string) GlobalSetting::get('tenant_currency_preset', 'global_common'));

        return array_key_exists($preset, $this->catalog->presets()) ? $preset : 'global_common';
    }

    public function forSurface(string $surface): string
    {
        return match ($surface) {
            'admin_dashboard', 'admin' => $this->adminCurrency(),
            'public_plans', 'public' => $this->publicCurrency(),
            'tenant_dashboard', 'tenant' => $this->tenantCurrency(),
            default => $this->base(),
        };
    }

    private function pick(string $code, array $allowed, ?string $scope = null): string
    {
        $resolved = $this->catalog->resolveCodes([$code], $scope);

        if ($resolved !== [] && in_array($resolved[0], $allowed, true)) {
            return $resolved[0];
        }

        return in_array($this->base(), $allowed, true) ? $this->base() : ($allowed[0] ?? $this->base());
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_scalar'));
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_scalar')) : [];
    }
}
