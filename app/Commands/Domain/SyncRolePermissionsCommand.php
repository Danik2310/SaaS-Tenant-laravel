<?php

namespace App\Commands\Domain;

use App\Models\Role;

class SyncRolePermissionsCommand
{
    private Role $role;
    private array $permissionIds;
    private array $previousPermissionIds;

    public function __construct(Role $role, array $permissionIds)
    {
        $this->role = $role;
        $this->permissionIds = $permissionIds;
        $this->previousPermissionIds = $role->permissions()->pluck('id')->toArray();
    }

    public function execute(): void
    {
        $service = app(\App\Contracts\PermissionServiceInterface::class);
        $service->syncPermissions($this->role, $this->permissionIds);
    }

    public function undo(): void
    {
        $this->role->syncPermissions($this->previousPermissionIds);
    }
}
