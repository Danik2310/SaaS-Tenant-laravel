<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->route('id');

        return [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:tenants,email,' . $tenantId,
            'status' => 'nullable|in:Active,Suspended,Deleted',
            'plan_id' => 'nullable|integer|exists:plans,id',
        ];
    }
}
