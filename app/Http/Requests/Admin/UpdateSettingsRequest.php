<?php

namespace App\Http\Requests\Admin;

use App\Shared\Constants\PermissionNames;
use App\Shared\Contracts\CurrencyCatalogInterface;
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
        'currency_mode',
        'currency_preset',
        'enabled_currencies',
        'currency_scope',
        'currency_admin',
        'currency_public',
        'tenant_currency_mode',
        'tenant_currency_preset',
        'tenant_currencies',
        'maintenance_mode',
        'max_upload_size',
        'session_lifetime',
        'tenant_db_prefix',
        'allow_registration',
        'default_plan_id',
    ];

    private const CURRENCY_KEYS = ['currency', 'currency_admin', 'currency_public'];

    private const CURRENCY_ARRAY_KEYS = ['enabled_currencies', 'tenant_currencies'];

    private const MODE_KEYS = ['currency_mode', 'tenant_currency_mode'];

    private const PRESET_KEYS = ['currency_preset', 'tenant_currency_preset'];

    private const SCOPES = ['admin_dashboard', 'public_plans', 'tenant_dashboards'];

    public function __construct(private CurrencyCatalogInterface $catalog)
    {
        parent::__construct();
    }

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
            $active = $this->catalog->active();
            $presets = array_keys($this->catalog->presets());

            foreach ($this->input('settings', []) as $index => $setting) {
                $key = (string) ($setting['key'] ?? '');
                $value = $setting['value'] ?? null;
                $attribute = "settings.{$index}.value";

                if (in_array($key, self::CURRENCY_KEYS, true)) {
                    $code = strtoupper((string) $value);
                    if (! isset($active[$code]) || ($key === 'currency_public' && ! $active[$code]['allow_public'])) {
                        $validator->errors()->add($attribute, "Unsupported currency '{$value}'.");
                    }
                }

                if (in_array($key, self::MODE_KEYS, true)
                    && ! in_array((string) $value, ['preset', 'custom'], true)) {
                    $validator->errors()->add($attribute, 'Invalid currency mode.');
                }

                if (in_array($key, self::PRESET_KEYS, true)
                    && ! in_array((string) $value, $presets, true)) {
                    $validator->errors()->add($attribute, 'Unknown currency preset.');
                }

                if ($key === 'currency_scope') {
                    $scopes = $this->decode($value);
                    if ($scopes === [] || array_diff($scopes, self::SCOPES) !== []) {
                        $validator->errors()->add($attribute, 'Choose at least one valid currency scope.');
                    }
                }

                if (in_array($key, self::CURRENCY_ARRAY_KEYS, true)) {
                    $codes = array_map('strtoupper', $this->decode($value));
                    if ($codes === []) {
                        $validator->errors()->add($attribute, 'Choose at least one currency.');

                        continue;
                    }

                    foreach ($codes as $code) {
                        if (! isset($active[$code])) {
                            $validator->errors()->add($attribute, "Unsupported currency '{$code}'.");
                        }

                        if ($key === 'tenant_currencies' && isset($active[$code]) && ! $active[$code]['allow_tenant']) {
                            $validator->errors()->add($attribute, "Currency '{$code}' is not available to tenants.");
                        }
                    }
                }
            }
        });
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
