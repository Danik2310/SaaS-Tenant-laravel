<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Tenant;
use App\Shared\Constants\PermissionNames;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class DeleteTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can(PermissionNames::DELETE_TENANTS);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenant = Tenant::findOrFail($this->route('id'));

        return [
            'workspace_name' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($tenant): void {
                    if (! hash_equals((string) $tenant->name, trim((string) $value))) {
                        $fail('The workspace name does not match.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'workspace_name.required' => 'Please type the workspace name.',
        ];
    }
}
