<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImpersonateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can('impersonate tenants');
    }

    public function rules(): array
    {
        return [
            'tenant_id' => 'required|string|exists:tenants,id',
        ];
    }
}
