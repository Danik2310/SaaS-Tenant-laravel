<?php

namespace App\Http\Requests\Admin;

use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    private const ALLOWED_KEYS = [
        'app_name',
        'app_url',
        'app_description',
        'support_email',
        'default_locale',
        'currency',
        'maintenance_mode',
        'max_upload_size',
        'session_lifetime',
        'tenant_db_prefix',
        'allow_registration',
        'default_plan_id',
    ];

    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can(PermissionNames::EDIT_SETTINGS);
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $supported = array_keys(config('currency.currencies'));

            foreach ($this->input('settings', []) as $index => $setting) {
                if (($setting['key'] ?? null) !== 'currency') {
                    continue;
                }

                $code = strtoupper((string) ($setting['value'] ?? ''));

                if (! in_array($code, $supported, true)) {
                    $validator->errors()->add(
                        "settings.{$index}.value",
                        "Unsupported currency '{$setting['value']}'. Choose from: ".implode(', ', $supported).'.'
                    );
                }
            }
        });
    }
}
