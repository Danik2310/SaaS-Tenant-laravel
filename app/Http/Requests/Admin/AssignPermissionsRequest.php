<?php

namespace App\Http\Requests\Admin;

use App\Models\Permission;
use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can(PermissionNames::EDIT_STAFF);
    }

    public function rules(): array
    {
        return [
            'permission_ids' => 'required|array',
            'permission_ids.*' => Rule::exists('permissions', 'id')->where('guard_name', 'admin'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->any()) {
                return;
            }

            $permissionIds = $this->input('permission_ids', []);
            $inactivePermissions = Permission::whereIn('id', $permissionIds)
                ->where('is_active', false)
                ->pluck('name')
                ->all();

            if (! empty($inactivePermissions)) {
                $validator->errors()->add(
                    'permission_ids',
                    'Cannot assign inactive permissions: '.implode(', ', $inactivePermissions)
                );
            }
        });
    }
}
