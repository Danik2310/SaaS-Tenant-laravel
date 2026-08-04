<?php

namespace App\Http\Requests\Admin;

use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can(PermissionNames::CREATE_STAFF);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admin_users,email',
            'password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
            'roles' => 'sometimes|array',
            'roles.*' => Rule::exists('roles', 'id')->where('guard_name', 'admin'),
            'is_active' => 'sometimes|boolean',
        ];
    }
}
