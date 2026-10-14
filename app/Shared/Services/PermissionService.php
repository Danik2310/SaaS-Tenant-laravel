<?php

namespace App\Shared\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Shared\Contracts\PermissionServiceInterface;
use Illuminate\Support\Facades\Auth;

/**
 * Permission assignment service.
 *
 * Guards role/direct-permission writes with two rules before persisting:
 *  1. **Privilege hierarchy** — an admin may only grant permissions they
 *     themselves hold.
 *  2. **Prerequisites** — dependent permissions must come with their
 *     prerequisites (delegated to {@see PermissionPrerequisiteValidator}).
 */
class PermissionService implements PermissionServiceInterface
{
    public function syncPermissions(Role $role, array $permissionIds): void
    {
        $this->validatePrivilegeHierarchy($permissionIds);
        $this->validateDependencies($permissionIds);

        $role->syncPermissions($permissionIds);
    }

    public function validateDirectPermissionAssignment(array $permissionIds): void
    {
        $this->validatePrivilegeHierarchy($permissionIds);
        $this->validateDependencies($permissionIds);
    }

    private function validatePrivilegeHierarchy(array $permissionIds): void
    {
        $user = Auth::guard('admin')->user();

        if (! $user) {
            abort(403);
        }

        $assigningPermissions = Permission::whereIn('id', $permissionIds)->pluck('name')->all();

        foreach ($assigningPermissions as $permission) {
            if (! $user->can($permission)) {
                abort(403, "You cannot assign the '{$permission}' permission because you do not have it yourself.");
            }
        }
    }

    private function validateDependencies(array $permissionIds): void
    {
        $permissionNames = Permission::whereIn('id', $permissionIds)
            ->pluck('name')
            ->all();

        $validator = app(PermissionPrerequisiteValidator::class);
        $errors = $validator->validateAll($permissionNames);

        if (! empty($errors)) {
            $messages = collect($errors)->map(
                fn (array $error, string $perm) => "The permission '{$perm}' requires: "
                    .implode(', ', $error['missing'])
                    .'. '.$error['explanation']
            )->values()->all();

            abort(422, implode(' ', $messages));
        }
    }
}
