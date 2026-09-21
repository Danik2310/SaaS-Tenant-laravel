<?php

namespace App\Shared\Services;

use App\Models\AdminUser;
use App\Models\Role;
use App\Shared\Constants\PermissionNames;
use App\Shared\Contracts\RoleServiceInterface;
use Illuminate\Support\Facades\Auth;

class RoleService implements RoleServiceInterface
{
    public function syncRoles(AdminUser $user, array $roleIds): void
    {
        $roles = Role::whereIn('id', $roleIds)
            ->where('guard_name', 'admin')
            ->get();

        $this->validatePrivilegeHierarchy($roles);

        $this->assertRoleChangeAllowed($user, $roles);

        $user->syncRoles($roles);
    }

    /**
     * Protect against a total system crash:
     *
     * - A super-admin cannot change the roles of their own account.
     * - The super-admin role cannot be removed from the last active super-admin.
     */
    private function assertRoleChangeAllowed(AdminUser $user, $newRoles): void
    {
        $currentUser = Auth::guard('admin')->user();

        if (! $currentUser) {
            abort(403);
        }

        $isSelf = $currentUser->getKey() === $user->getKey();
        $targetIsSuperAdmin = $user->hasRole(PermissionNames::ROLE_SUPER_ADMIN);

        if ($isSelf && $targetIsSuperAdmin) {
            abort(422, 'You cannot change the roles of your own Administrator account.');
        }

        $stillSuperAdmin = $newRoles->contains(fn ($role) => $role->name === PermissionNames::ROLE_SUPER_ADMIN);

        if ($targetIsSuperAdmin && ! $stillSuperAdmin) {
            $otherSuperAdminExists = AdminUser::query()
                ->where('id', '!=', $user->getKey())
                ->where('is_active', true)
                ->role(PermissionNames::ROLE_SUPER_ADMIN, 'admin')
                ->exists();

            if (! $otherSuperAdminExists) {
                abort(422, 'Cannot remove the super-admin role from the last administrator account.');
            }
        }
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
