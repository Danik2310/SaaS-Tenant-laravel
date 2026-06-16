<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImpersonateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can('impersonate tenants');
    }

    public function rules(): array
    {
        return [
            'tenant_id' => [
                'required',
                'string',
                Rule::exists('tenants', 'id')->where(function ($query) {
                    $query->whereNull('deleted_at')
                        ->whereIn('status', ['Active', 'Trial']);
                }),
            ],
        ];
    }
}
