<?php

namespace App\Shared\Contracts;

use App\Models\AdminUser;

interface RoleServiceInterface
{
    public function syncRoles(AdminUser $user, array $roleIds): void;
}
