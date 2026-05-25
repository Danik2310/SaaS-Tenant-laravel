<?php

namespace App\Contracts;

use App\Models\Role;

interface PermissionServiceInterface
{
    public function syncPermissions(Role $role, array $permissionIds): void;
}
