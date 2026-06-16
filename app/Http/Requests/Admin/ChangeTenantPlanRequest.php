<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ChangeTenantPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can('manage tenants');
    }

    public function rules(): array
    {
        return [
            'plan_id' => 'required|integer|exists:plans,id',
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.required' => 'A target plan is required.',
            'plan_id.exists' => 'The selected plan does not exist.',
        ];
    }
}
