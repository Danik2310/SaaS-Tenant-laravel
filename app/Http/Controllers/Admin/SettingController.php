<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SettingResource;
use App\Models\GlobalSetting;
use Illuminate\Http\Request;

/**
 * @group Global Settings
 *
 * APIs for managing global application settings.
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
        $settings = GlobalSetting::orderBy('key')->get();

        return response()->json([
            'data' => SettingResource::collection($settings),
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
    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|max:255',
            'settings.*.value' => 'nullable|string|max:65535',
        ]);

        foreach ($validated['settings'] as $setting) {
            GlobalSetting::set($setting['key'], $setting['value']);
        }

        activity()->log('Updated global settings');

        return response()->json([
            'message' => 'Settings updated successfully',
            'data' => SettingResource::collection(GlobalSetting::orderBy('key')->get()),
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
            'data' => new SettingResource($setting),
        ]);
    }
}
