<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $staffId = $this->route('id');

        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:admin_users,email,'.$staffId,
            'password' => ['sometimes', Password::min(8)->mixedCase()->numbers()->symbols()],
            'roles' => 'sometimes|array',
            'roles.*' => 'exists:roles,id',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
