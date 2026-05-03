<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class TenantRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';

        $permissions = [
            ['name' => 'manage customers', 'description' => 'Manage customer records', 'module' => 'customers'],
            ['name' => 'manage products', 'description' => 'Manage products and catalog', 'module' => 'products'],
            ['name' => 'manage categories', 'description' => 'Manage product categories', 'module' => 'categories'],
            ['name' => 'manage orders', 'description' => 'Manage customer orders', 'module' => 'orders'],
            ['name' => 'manage inventory', 'description' => 'Manage inventory and stock movements', 'module' => 'inventory'],
            ['name' => 'manage payments', 'description' => 'Manage payments and transactions', 'module' => 'payments'],
            ['name' => 'manage settings', 'description' => 'Manage tenant settings', 'module' => 'settings'],
            ['name' => 'view reports', 'description' => 'View analytics and reports', 'module' => 'reports'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(
                ['name' => $perm['name'], 'guard_name' => $guard],
                [
                    'description' => $perm['description'],
                    'module' => $perm['module'],
                    'is_active' => true,
                ]
            );
        }

        $adminRole = Role::updateOrCreate(
            ['name' => 'tenant-admin', 'guard_name' => $guard],
            ['description' => 'Full access to tenant features', 'is_active' => true]
        );
        $adminRole->givePermissionTo($permissions);

        $managerRole = Role::updateOrCreate(
            ['name' => 'manager', 'guard_name' => $guard],
            ['description' => 'Can manage orders, products, and customers', 'is_active' => true]
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
            ['name' => 'cashier', 'guard_name' => $guard],
            ['description' => 'Can process orders and payments', 'is_active' => true]
        );
        $cashierRole->givePermissionTo([
            'manage customers',
            'manage orders',
            'manage payments',
        ]);
    }
}
