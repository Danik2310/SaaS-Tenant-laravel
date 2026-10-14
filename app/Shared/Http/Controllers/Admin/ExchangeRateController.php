<?php

namespace App\Shared\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Shared\Contracts\ExchangeRateServiceInterface;

/**
 * @group Global Settings
 *
 * APIs for managing global application settings.
 *
 * Flow: `index()` returns cached USD-based rates plus the admin display currency
 * from the global settings.
 */
class ExchangeRateController extends Controller
{
    /**
     * Get exchange rates and supported currencies.
     *
     * Returns the cached USD-based exchange rates for the supported currency
     * list, together with the admin display currency from the global settings.
     *
     * @authenticated
     */
    public function index(ExchangeRateServiceInterface $service)
    {
        return response()->json([
            'base' => config('currency.base_currency'),
            'display_currency' => $service->displayCurrency(),
            'rates' => $service->rates(),
            'currencies' => $service->currencies(),
            'updated_at' => $service->updatedAt(),
            'is_live' => $service->isLive(),
        ]);
    }
}
