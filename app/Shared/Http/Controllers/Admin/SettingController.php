<?php

namespace App\Shared\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Http\Resources\SettingResource;
use App\Models\GlobalSetting;
use App\Shared\Contracts\ExchangeRateServiceInterface;
use Illuminate\Support\Facades\Cache;

/**
 * @group Global Settings
 *
 * APIs for managing global application settings.
 *
 * Flow: `index()`/`get()` read through the forever `global_settings` cache;
 * `update()` persists each key, forgets the cache, and writes an activity entry.
 */
class SettingController extends Controller
{
    /**
     * List all settings.
     *
     * @authenticated
     */
    public function index()
    {
        $settings = Cache::rememberForever('global_settings', function () {
            return GlobalSetting::orderBy('key')->get();
        });

        return response()->json([
            'settings' => SettingResource::collection($settings),
        ]);
    }

    /**
     * Update settings.
     *
     * @authenticated
     *
     * @bodyParam settings array required Array of settings with key and value pairs.
     * @bodyParam settings.*.key string required Setting key.
     * @bodyParam settings.*.value string required Setting value.
     */
    public function update(UpdateSettingsRequest $request, ExchangeRateServiceInterface $exchangeRates)
    {
        $validated = $request->validated();
        $touchesCurrency = false;

        foreach ($validated['settings'] as $setting) {
            GlobalSetting::set($setting['key'], $setting['value']);
            $touchesCurrency = $touchesCurrency || str_starts_with((string) $setting['key'], 'currency')
                || str_starts_with((string) $setting['key'], 'tenant_currency')
                || in_array($setting['key'], ['enabled_currencies', 'tenant_currencies'], true);
        }

        Cache::forget('global_settings');

        if ($touchesCurrency) {
            $exchangeRates->forgetCache();
        }

        activity()->log('Updated global settings');

        return response()->json([
            'message' => 'Settings updated successfully',
            'settings' => SettingResource::collection(GlobalSetting::orderBy('key')->get()),
        ]);
    }

    /**
     * Get a single setting by key.
     *
     * @authenticated
     *
     * @urlParam key string required The setting key.
     *
     * @response 404 {"message":"Setting not found"}
     */
    public function get(string $key)
    {
        $setting = GlobalSetting::where('key', $key)->first();

        if (! $setting) {
            return response()->json(['message' => 'Setting not found'], 404);
        }

        return response()->json([
            'setting' => new SettingResource($setting),
        ]);
    }
}
