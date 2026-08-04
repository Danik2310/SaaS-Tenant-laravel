<?php

namespace Database\Seeders;

use App\Shared\Constants\PermissionNames;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CentralRolePermissionSeeder extends Seeder
{
    /**
     * Permissions removed when the catalog was split into granular CRUD actions.
     *
     * @var array<int, string>
     */
    private const DEPRECATED_PERMISSIONS = [
        PermissionNames::DEPRECATED_MANAGE_TENANTS,
        PermissionNames::DEPRECATED_MANAGE_STAFF,
        PermissionNames::DEPRECATED_MANAGE_PLANS,
        PermissionNames::DEPRECATED_MANAGE_PAYMENT_METHODS,
        PermissionNames::DEPRECATED_MANAGE_SUBSCRIPTIONS,
        PermissionNames::DEPRECATED_MANAGE_SETTINGS,
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only create central permissions for the 'admin' guard
        // Tenant-level permissions are created by TenantUserRolePermissionSeeder
        $guard = 'admin';

        $permissions = [
            // Tenants
            ['name' => PermissionNames::VIEW_TENANTS, 'module' => 'tenants', 'description' => 'Permite ver la lista y los detalles de los tenants'],
            ['name' => PermissionNames::CREATE_TENANTS, 'module' => 'tenants', 'description' => 'Permite crear nuevos tenants'],
            ['name' => PermissionNames::EDIT_TENANTS, 'module' => 'tenants', 'description' => 'Permite editar tenants, cambiar su plan, migrar y gestionar su estado'],
            ['name' => PermissionNames::DELETE_TENANTS, 'module' => 'tenants', 'description' => 'Permite eliminar tenants'],
            ['name' => PermissionNames::RESTORE_TENANTS, 'module' => 'tenants', 'description' => 'Permite restaurar tenants eliminados'],
            ['name' => PermissionNames::IMPERSONATE_TENANTS, 'module' => 'tenants', 'description' => 'Permite impersonar tenants para acceder a sus dominios'],
            // Staff
            ['name' => PermissionNames::VIEW_STAFF, 'module' => 'staff', 'description' => 'Permite ver la lista y los detalles del personal administrativo'],
            ['name' => PermissionNames::CREATE_STAFF, 'module' => 'staff', 'description' => 'Permite crear personal administrativo'],
            ['name' => PermissionNames::EDIT_STAFF, 'module' => 'staff', 'description' => 'Permite editar personal administrativo, asignar roles y cambiar su estado'],
            ['name' => PermissionNames::DELETE_STAFF, 'module' => 'staff', 'description' => 'Permite eliminar personal administrativo'],
            // Roles
            ['name' => PermissionNames::VIEW_ROLES, 'module' => 'roles', 'description' => 'Permite ver los roles del sistema'],
            ['name' => PermissionNames::CREATE_ROLES, 'module' => 'roles', 'description' => 'Permite crear roles'],
            ['name' => PermissionNames::EDIT_ROLES, 'module' => 'roles', 'description' => 'Permite editar roles y sus permisos'],
            ['name' => PermissionNames::DELETE_ROLES, 'module' => 'roles', 'description' => 'Permite eliminar roles'],
            // Permissions
            ['name' => PermissionNames::VIEW_PERMISSIONS, 'module' => 'permissions', 'description' => 'Permite ver el catálogo de permisos'],
            ['name' => PermissionNames::CREATE_PERMISSIONS, 'module' => 'permissions', 'description' => 'Permite crear permisos'],
            ['name' => PermissionNames::EDIT_PERMISSIONS, 'module' => 'permissions', 'description' => 'Permite editar permisos'],
            ['name' => PermissionNames::DELETE_PERMISSIONS, 'module' => 'permissions', 'description' => 'Permite eliminar permisos'],
            // Plans
            ['name' => PermissionNames::VIEW_PLANS, 'module' => 'plans', 'description' => 'Permite ver planes de suscripción y precios'],
            ['name' => PermissionNames::CREATE_PLANS, 'module' => 'plans', 'description' => 'Permite crear planes de suscripción'],
            ['name' => PermissionNames::EDIT_PLANS, 'module' => 'plans', 'description' => 'Permite editar planes de suscripción y precios'],
            ['name' => PermissionNames::DELETE_PLANS, 'module' => 'plans', 'description' => 'Permite eliminar planes de suscripción'],
            ['name' => PermissionNames::MANAGE_FEATURE_FLAGS, 'module' => 'plans', 'description' => 'Permite gestionar los feature flags de los planes'],
            // Billing
            ['name' => PermissionNames::VIEW_SUBSCRIPTIONS, 'module' => 'billing', 'description' => 'Permite ver las suscripciones de los tenants'],
            ['name' => PermissionNames::MANAGE_SUBSCRIPTION_PAYMENTS, 'module' => 'billing', 'description' => 'Permite crear y editar pagos de suscripciones'],
            ['name' => PermissionNames::VIEW_PAYMENT_METHODS, 'module' => 'billing', 'description' => 'Permite ver los métodos de pago'],
            ['name' => PermissionNames::CREATE_PAYMENT_METHODS, 'module' => 'billing', 'description' => 'Permite crear métodos de pago'],
            ['name' => PermissionNames::EDIT_PAYMENT_METHODS, 'module' => 'billing', 'description' => 'Permite editar métodos de pago y su estado'],
            ['name' => PermissionNames::DELETE_PAYMENT_METHODS, 'module' => 'billing', 'description' => 'Permite eliminar métodos de pago'],
            // System
            ['name' => PermissionNames::VIEW_SETTINGS, 'module' => 'system', 'description' => 'Permite ver las configuraciones globales del sistema'],
            ['name' => PermissionNames::EDIT_SETTINGS, 'module' => 'system', 'description' => 'Permite gestionar las configuraciones globales del sistema'],
            ['name' => PermissionNames::VIEW_ACTIVITY_LOGS, 'module' => 'system', 'description' => 'Permite ver registros de actividad del sistema'],
            // Profile
            ['name' => PermissionNames::MANAGE_PROFILE, 'module' => 'profile', 'description' => 'Permite gestionar el perfil personal'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => $guard],
                [
                    'description' => $permission['description'],
                    'module' => $permission['module'],
                    'is_active' => true,
                ]
            );
        }

        $this->removeDeprecatedPermissions($guard);

        // Create roles for this guard
        $superAdmin = Role::updateOrCreate(
            ['name' => PermissionNames::ROLE_SUPER_ADMIN, 'guard_name' => $guard],
            [
                'description' => 'Administrador con acceso completo a todas las funciones',
                'is_active' => true,
            ]
        );
        $superAdmin->syncPermissions(
            Permission::where('guard_name', $guard)
                ->where('is_active', true)
                ->pluck('id')
                ->all()
        );

        $staff = Role::updateOrCreate(
            ['name' => PermissionNames::ROLE_STAFF, 'guard_name' => $guard],
            [
                'description' => 'Personal administrativo con permisos de solo lectura',
                'is_active' => true,
            ]
        );
        $staff->syncPermissions([
            PermissionNames::VIEW_TENANTS,
            PermissionNames::VIEW_PLANS,
            PermissionNames::VIEW_SUBSCRIPTIONS,
            PermissionNames::VIEW_PAYMENT_METHODS,
            PermissionNames::VIEW_ACTIVITY_LOGS,
            PermissionNames::MANAGE_PROFILE,
        ]);
    }

    /**
     * Detach and remove permissions replaced by the granular CRUD catalog.
     */
    private function removeDeprecatedPermissions(string $guard): void
    {
        $deprecated = Permission::where('guard_name', $guard)
            ->whereIn('name', self::DEPRECATED_PERMISSIONS)
            ->get();

        foreach ($deprecated as $permission) {
            $permission->roles()->detach();
            $permission->delete();
        }
    }
}
