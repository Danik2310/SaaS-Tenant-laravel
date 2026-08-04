<?php

namespace Tests\Support;

use App\Models\AdminUser;
use App\Models\Permission;
use App\Models\Role;

trait AdminAuthSetup
{
    protected function setUpAdminAuth(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'admin',
        ]);

        $permissions = [
            // Tenants
            'view tenants',
            'create tenants',
            'edit tenants',
            'delete tenants',
            'restore tenants',
            'impersonate tenants',
            // Staff
            'view staff',
            'create staff',
            'edit staff',
            'delete staff',
            // Roles
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
            // Permissions
            'view permissions',
            'create permissions',
            'edit permissions',
            'delete permissions',
            // Plans
            'view plans',
            'create plans',
            'edit plans',
            'delete plans',
            'manage feature flags',
            // Billing
            'view subscriptions',
            'manage subscription payments',
            'view payment methods',
            'create payment methods',
            'edit payment methods',
            'delete payment methods',
            // System
            'view settings',
            'edit settings',
            'view activity logs',
            // Profile
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
