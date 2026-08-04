<?php

namespace App\Http\Requests\Admin;

use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can(PermissionNames::EDIT_PERMISSIONS);
    }

    public function rules(): array
    {
        $permissionId = $this->route('id');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('permissions', 'name')
                    ->ignore($permissionId)
                    ->where('guard_name', 'admin'),
            ],
            'description' => 'nullable|string|max:500',
            'module' => 'required|string|max:100',
        ];
    }
}
