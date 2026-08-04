<?php

namespace App\Http\Requests\Admin;

use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can(PermissionNames::EDIT_STAFF);
    }

    public function rules(): array
    {
        $staffId = $this->route('id');

        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:admin_users,email,'.$staffId,
            'password' => ['sometimes', Password::min(8)->mixedCase()->numbers()->symbols()],
            'roles' => 'sometimes|array',
            'roles.*' => Rule::exists('roles', 'id')->where('guard_name', 'admin'),
            'is_active' => 'sometimes|boolean',
        ];
    }
}
