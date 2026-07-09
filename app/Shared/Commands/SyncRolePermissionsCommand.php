<?php

namespace App\Shared\Commands;

use App\Models\Role;
use App\Shared\Contracts\CommandInterface;
use App\Shared\Contracts\PermissionServiceInterface;

class SyncRolePermissionsCommand implements CommandInterface
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

    public function execute(): mixed
    {
        $service = app(PermissionServiceInterface::class);
        $service->syncPermissions($this->role, $this->permissionIds);

        return null;
    }

    public function rollback(): void
    {
        $this->role->syncPermissions($this->previousPermissionIds);
    }
}
