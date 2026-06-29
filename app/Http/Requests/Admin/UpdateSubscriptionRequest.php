<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can('manage subscriptions');
    }

    public function rules(): array
    {
        $rules = [
            'plan_id' => 'sometimes|integer|exists:mysql_central.plans,id',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'nullable|date',
            'status' => 'sometimes|string|in:active,pending,cancelled,expired',
        ];

        if ($this->has('starts_at') && $this->has('ends_at')) {
            $rules['ends_at'] = 'nullable|date|after:starts_at';
        }

        return $rules;
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
