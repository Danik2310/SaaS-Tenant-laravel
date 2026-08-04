<?php

namespace Database\Seeders;

use App\Shared\Constants\PermissionNames;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TenantUserRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';

        $permissions = [
            PermissionNames::MANAGE_CUSTOMERS,
            PermissionNames::MANAGE_PRODUCTS,
            PermissionNames::MANAGE_CATEGORIES,
            PermissionNames::MANAGE_ORDERS,
            PermissionNames::MANAGE_INVENTORY,
            PermissionNames::MANAGE_PAYMENTS,
            PermissionNames::MANAGE_SETTINGS,
            PermissionNames::VIEW_REPORTS,
        ];

        $createdPermissions = [];
        foreach ($permissions as $name) {
            $perm = Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => $guard]
            );
            $createdPermissions[] = $perm;
        }

        $adminRole = Role::updateOrCreate(
            ['name' => PermissionNames::ROLE_TENANT_ADMIN, 'guard_name' => $guard]
        );
        $adminRole->givePermissionTo($createdPermissions);

        $managerRole = Role::updateOrCreate(
            ['name' => PermissionNames::ROLE_MANAGER, 'guard_name' => $guard]
        );
        $managerRole->givePermissionTo([
            PermissionNames::MANAGE_CUSTOMERS,
            PermissionNames::MANAGE_PRODUCTS,
            PermissionNames::MANAGE_CATEGORIES,
            PermissionNames::MANAGE_ORDERS,
            PermissionNames::MANAGE_INVENTORY,
            PermissionNames::VIEW_REPORTS,
        ]);

        $cashierRole = Role::updateOrCreate(
            ['name' => PermissionNames::ROLE_CASHIER, 'guard_name' => $guard]
        );
        $cashierRole->givePermissionTo([
            PermissionNames::MANAGE_CUSTOMERS,
            PermissionNames::MANAGE_ORDERS,
            PermissionNames::MANAGE_PAYMENTS,
        ]);
    }
}
