<?php

namespace App\Commands\Domain;

use App\Contracts\CommandInterface;
use App\Contracts\RoleServiceInterface;
use App\Models\AdminUser;

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
