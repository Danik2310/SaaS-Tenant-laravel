<?php

namespace App\Http\Requests\Admin;

use App\Shared\Constants\PermissionNames;
use App\Shared\Rules\PermissionPrerequisitesRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can(PermissionNames::EDIT_ROLES);
    }

    public function rules(): array
    {
        $roleId = $this->route('id');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')
                    ->ignore($roleId)
                    ->where('guard_name', 'admin'),
            ],
            'description' => 'nullable|string|max:500',
            'permissions' => ['sometimes', 'array', new PermissionPrerequisitesRule],
            'permissions.*' => Rule::exists('permissions', 'id')->where('guard_name', 'admin'),
            'is_active' => 'sometimes|boolean',
        ];
    }
}
