<?php

namespace App\Http\Requests\Admin;

use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can(PermissionNames::EDIT_STAFF);
    }

    public function rules(): array
    {
        return [
            'role_ids' => 'required|array',
            'role_ids.*' => Rule::exists('roles', 'id')->where('guard_name', 'admin'),
        ];
    }
}
