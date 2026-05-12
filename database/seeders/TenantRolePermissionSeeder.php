<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TenantRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';

        $permissions = [
            'manage customers',
            'manage products',
            'manage categories',
            'manage orders',
            'manage inventory',
            'manage payments',
            'manage settings',
            'view reports',
        ];

        $createdPermissions = [];
        foreach ($permissions as $name) {
            $perm = Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => $guard]
            );
            $createdPermissions[] = $perm;
        }

        $adminRole = Role::updateOrCreate(
            ['name' => 'tenant-admin', 'guard_name' => $guard]
        );
        $adminRole->givePermissionTo($createdPermissions);

        $managerRole = Role::updateOrCreate(
            ['name' => 'manager', 'guard_name' => $guard]
        );
        $managerRole->givePermissionTo([
            'manage customers',
            'manage products',
            'manage categories',
            'manage orders',
            'manage inventory',
            'view reports',
        ]);

        $cashierRole = Role::updateOrCreate(
            ['name' => 'cashier', 'guard_name' => $guard]
        );
        $cashierRole->givePermissionTo([
            'manage customers',
            'manage orders',
            'manage payments',
        ]);
    }
}
