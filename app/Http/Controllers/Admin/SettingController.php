<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GlobalSetting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = GlobalSetting::orderBy('key')->get()->map(function ($setting) {
            return [
                'id' => $setting->id,
                'key' => $setting->key,
                'value' => $setting->value,
                'updated_at' => $setting->updated_at?->format('Y-m-d H:i'),
            ];
        });

        return response()->json([
            'settings' => $settings,
        ]);
    }

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
            'settings' => GlobalSetting::orderBy('key')->get(),
        ]);
    }

    public function get(string $key)
    {
        $setting = GlobalSetting::where('key', $key)->first();

        if (! $setting) {
            return response()->json(['message' => 'Setting not found'], 404);
        }

        return response()->json([
            'setting' => [
                'id' => $setting->id,
                'key' => $setting->key,
                'value' => $setting->value,
            ],
        ]);
    }
}
