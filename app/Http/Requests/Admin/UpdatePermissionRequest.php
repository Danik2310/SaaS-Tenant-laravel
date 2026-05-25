<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can('manage staff');
    }

    public function rules(): array
    {
        $permissionId = $this->route('id');

        return [
            'name' => 'required|string|max:255|unique:permissions,name,'.$permissionId,
            'description' => 'nullable|string|max:500',
            'module' => 'required|string|max:100',
        ];
    }
}
