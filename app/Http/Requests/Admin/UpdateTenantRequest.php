<?php

namespace App\Http\Requests\Admin;

use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can(PermissionNames::EDIT_TENANTS);
    }

    public function rules(): array
    {
        $tenantId = $this->route('id');

        return [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:tenants,email,'.$tenantId,
            'status' => 'nullable|in:Active,Suspended',
            'plan_id' => 'nullable|integer|exists:mysql_central.plans,id',
        ];
    }
}
