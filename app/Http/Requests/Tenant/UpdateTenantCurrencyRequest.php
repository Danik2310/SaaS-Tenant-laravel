<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Shared\Constants\PermissionNames;
use App\Shared\Contracts\CurrencySettingsInterface;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantCurrencyRequest extends FormRequest
{
    public function __construct(private CurrencySettingsInterface $settings)
    {
        parent::__construct();
    }

    public function authorize(): bool
    {
        return auth('web')->check() && auth('web')->user()->can(PermissionNames::MANAGE_SETTINGS);
    }

    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', 'size:3'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $code = strtoupper((string) $this->input('currency'));

            if (! in_array($code, $this->settings->tenantCurrencies(), true)) {
                $validator->errors()->add('currency', 'This currency is not approved for tenant dashboards.');
            }
        });
    }
}
