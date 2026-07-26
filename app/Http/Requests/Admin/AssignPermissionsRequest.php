<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AssignPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can('manage staff');
    }

    public function rules(): array
    {
        return [
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ];
    }
}
