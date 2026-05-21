<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth('admin')->user();

        if (! $user) {
            return false;
        }

        return match ($this->route('entity')) {
            'tenants' => $user->can('manage tenants'),
            'subscriptions' => $user->can('manage subscriptions'),
            'staff' => $user->can('manage staff'),
            'plans' => $user->can('manage plans'),
            'activity-logs' => $user->can('view activity logs'),
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'format' => ['sometimes', 'string', 'in:csv,xlsx'],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string'],
            'filters' => ['sometimes', 'array'],
            'filters.status' => ['sometimes', 'string'],
            'filters.plan_id' => ['sometimes', 'integer'],
            'filters.date_from' => ['sometimes', 'date'],
            'filters.date_to' => ['sometimes', 'date'],
            'filters.search' => ['sometimes', 'string'],
            'filters.log_name' => ['sometimes', 'string'],
            'filters.causer_id' => ['sometimes', 'integer'],
            'filters.is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'format.in' => 'Format must be csv or xlsx.',
            'entity' => 'Invalid entity type.',
        ];
    }
}
