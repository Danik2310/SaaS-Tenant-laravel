<?php

namespace App\Shared\Contracts;

use App\Models\Role;

interface PermissionServiceInterface
{
    public function syncPermissions(Role $role, array $permissionIds): void;

    public function validateDirectPermissionAssignment(array $permissionIds): void;
}
