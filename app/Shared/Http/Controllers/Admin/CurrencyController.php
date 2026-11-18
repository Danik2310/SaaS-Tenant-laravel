<?php

namespace App\Shared\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Shared\Contracts\CurrencyCatalogInterface;
use App\Shared\Contracts\CurrencySettingsInterface;

/**
 * @group Global Settings
 *
 * APIs for the administrator-managed currency catalog.
 */
class CurrencyController extends Controller
{
    /**
     * Get the currency catalog and the available presets.
     *
     * @authenticated
     */
    public function index(CurrencyCatalogInterface $catalog, CurrencySettingsInterface $settings)
    {
        return response()->json([
            'base' => $settings->base(),
            'settings' => [
                'mode' => $settings->mode(),
                'preset' => $settings->preset(),
                'enabled_currencies' => $settings->enabledCurrencies(),
                'scopes' => $settings->scopes(),
                'admin_currency' => $settings->adminCurrency(),
                'public_currency' => $settings->publicCurrency(),
                'tenant_mode' => $settings->tenantMode(),
                'tenant_preset' => $settings->tenantPreset(),
                'tenant_currencies' => $settings->tenantCurrencies(),
            ],
            'currencies' => array_values($catalog->all()),
            'active' => array_values($catalog->active()),
            'presets' => $catalog->presets(),
            'scopes' => ['admin_dashboard', 'public_plans', 'tenant_dashboards'],
        ]);
    }
}
