<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'tenant_id' => 'required|string|exists:tenants,id',
            'plan_id' => 'required|integer|exists:plans,id',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'status' => 'required|string|in:active,pending,cancelled,expired',
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_id.required' => 'A tenant must be selected.',
            'tenant_id.exists' => 'The selected tenant does not exist.',
            'plan_id.required' => 'A plan must be selected.',
            'plan_id.exists' => 'The selected plan does not exist.',
            'status.in' => 'Status must be one of: active, pending, cancelled, expired.',
            'ends_at.after' => 'End date must be after the start date.',
        ];
    }
}
