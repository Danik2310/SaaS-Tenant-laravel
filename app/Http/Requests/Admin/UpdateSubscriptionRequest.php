<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => 'sometimes|integer|exists:plans,id',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'status' => 'sometimes|string|in:active,pending,cancelled,expired',
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.exists' => 'The selected plan does not exist.',
            'status.in' => 'Status must be one of: active, pending, cancelled, expired.',
            'ends_at.after' => 'End date must be after the start date.',
        ];
    }
}
