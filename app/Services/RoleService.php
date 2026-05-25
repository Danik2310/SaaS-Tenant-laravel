<?php

namespace App\Services;

use App\Contracts\RoleServiceInterface;
use App\Models\AdminUser;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;

class RoleService implements RoleServiceInterface
{
    public function syncRoles(AdminUser $user, array $roleIds): void
    {
        $roles = Role::whereIn('id', $roleIds)
            ->where('guard_name', 'admin')
            ->get();

        $this->validatePrivilegeHierarchy($roles);

        $user->syncRoles($roles);
    }

    private function validatePrivilegeHierarchy($roles): void
    {
        $user = Auth::guard('admin')->user();

        if (! $user) {
            abort(403);
        }

        $rolePermissions = $roles->load('permissions')
            ->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->unique()
            ->all();

        foreach ($rolePermissions as $permission) {
            if (! $user->can($permission)) {
                abort(403, "You cannot assign the '{$permission}' permission because you do not have it yourself.");
            }
        }
    }
}
