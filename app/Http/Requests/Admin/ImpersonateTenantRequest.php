<?php

namespace App\Http\Requests\Admin;

use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImpersonateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can(PermissionNames::IMPERSONATE_TENANTS);
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
