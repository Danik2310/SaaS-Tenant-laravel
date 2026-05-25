<?php

namespace App\Http\Requests\Admin;

use App\Rules\PermissionPrerequisitesRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can('manage staff');
    }

    public function rules(): array
    {
        $roleId = $this->route('id');

        return [
            'name' => 'required|string|max:255|unique:roles,name,'.$roleId,
            'description' => 'nullable|string|max:500',
            'permissions' => ['sometimes', 'array', new PermissionPrerequisitesRule],
            'permissions.*' => 'exists:permissions,id',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
