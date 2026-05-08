<?php

namespace Tests\Support;

use App\Models\AdminUser;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

trait AdminAuthSetup
{
    protected function setUpAdminAuth(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'admin',
        ]);

        $permissions = [
            'manage tenants',
            'manage staff',
            'manage plans',
            'manage payment methods',
            'impersonate tenants',
            'manage profile',
        ];

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'admin',
            ]);
            $role->givePermissionTo($permission);
        }

        $admin = AdminUser::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin, 'admin');
    }
}
