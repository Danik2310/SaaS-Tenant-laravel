<?php

namespace App\Http\Requests\Admin;

use App\Rules\PermissionPrerequisitesRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string|max:500',
            'permissions' => ['sometimes', 'array', new PermissionPrerequisitesRule],
            'permissions.*' => 'exists:permissions,id',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
