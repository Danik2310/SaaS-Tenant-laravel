<?php

namespace App\Shared\Commands;

use App\Models\AdminUser;
use App\Shared\Contracts\CommandInterface;
use App\Shared\Contracts\RoleServiceInterface;

class SyncStaffRolesCommand implements CommandInterface
{
    private AdminUser $user;

    private array $roleIds;

    private array $previousRoleIds;

    public function __construct(AdminUser $user, array $roleIds)
    {
        $this->user = $user;
        $this->roleIds = $roleIds;
        $this->previousRoleIds = $user->roles()->pluck('id')->toArray();
    }

    public function execute(): mixed
    {
        $service = app(RoleServiceInterface::class);
        $service->syncRoles($this->user, $this->roleIds);

        return null;
    }

    public function rollback(): void
    {
        $this->user->syncRoles($this->previousRoleIds);
    }
}
