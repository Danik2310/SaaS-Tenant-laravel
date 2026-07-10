<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    private const ALLOWED_KEYS = [
        'app_name',
        'app_url',
        'support_email',
        'default_locale',
        'maintenance_mode',
        'max_upload_size',
        'session_lifetime',
    ];

    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can('manage settings');
    }

    public function rules(): array
    {
        return [
            'settings' => 'required|array',
            'settings.*.key' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (! in_array($value, self::ALLOWED_KEYS, true)) {
                        $fail("The setting key '{$value}' is not in the allowed list.");
                    }
                },
            ],
            'settings.*.value' => 'nullable|string|max:65535',
        ];
    }
}
