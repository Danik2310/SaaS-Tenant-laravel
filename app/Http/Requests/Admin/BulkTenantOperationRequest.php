<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkTenantOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'tenant_ids' => ['required', 'array', 'min:1', 'max:100'],
            'tenant_ids.*' => ['required', 'string', Rule::exists('tenants', 'id')->whereNull('deleted_at')],
            'action' => ['required', 'string', 'in:suspend,activate,delete,restore,change_plan,extend_trial'],
            'payload' => ['nullable', 'array'],
            'payload.plan_id' => ['required_if:action,change_plan', 'integer', 'exists:plans,id'],
            'payload.status' => ['prohibited'],
            'payload.days' => ['required_if:action,extend_trial', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_ids.required' => 'At least one tenant must be selected.',
            'tenant_ids.min' => 'At least one tenant must be selected.',
            'tenant_ids.max' => 'Cannot operate on more than 100 tenants at once.',
            'action.in' => 'Invalid action. Supported: suspend, activate, delete, restore, change_plan, extend_trial.',
            'payload.plan_id.required_if' => 'Plan ID is required when changing plans.',
            'payload.days.required_if' => 'Number of days is required when extending trials.',
        ];
    }
}
