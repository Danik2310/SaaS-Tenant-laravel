<?php

namespace App\Http\Requests\Admin;

use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can(PermissionNames::CREATE_PERMISSIONS);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('permissions', 'name')->where('guard_name', 'admin'),
            ],
            'description' => 'nullable|string|max:500',
            'module' => 'required|string|max:100',
        ];
    }
}
