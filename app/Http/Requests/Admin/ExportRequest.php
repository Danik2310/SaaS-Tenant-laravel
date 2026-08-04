<?php

namespace App\Http\Requests\Admin;

use App\Shared\Constants\PermissionNames;
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
            'tenants' => $user->can(PermissionNames::VIEW_TENANTS),
            'subscriptions' => $user->can(PermissionNames::VIEW_SUBSCRIPTIONS),
            'staff' => $user->can(PermissionNames::VIEW_STAFF),
            'plans' => $user->can(PermissionNames::VIEW_PLANS),
            'activity-logs' => $user->can(PermissionNames::VIEW_ACTIVITY_LOGS),
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
