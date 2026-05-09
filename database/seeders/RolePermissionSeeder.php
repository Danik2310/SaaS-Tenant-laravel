<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // define guards we care about
        $guards = ['web', 'admin'];

        foreach ($guards as $guard) {
            // Create permissions for this guard
            Permission::updateOrCreate(
                ['name' => 'manage tenants', 'guard_name' => $guard],
                [
                    'description' => 'Permite gestionar tenants: crear, editar, eliminar y ver información de tenants',
                    'module' => 'tenants',
                    'is_active' => true
                ]
            );
            Permission::updateOrCreate(
                ['name' => 'manage staff', 'guard_name' => $guard],
                [
                    'description' => 'Permite gestionar el personal administrativo',
                    'module' => 'staff',
                    'is_active' => true
                ]
            );
            Permission::updateOrCreate(
                ['name' => 'manage plans', 'guard_name' => $guard],
                [
                    'description' => 'Permite gestionar planes de suscripción y precios',
                    'module' => 'plans',
                    'is_active' => true
                ]
            );
            Permission::updateOrCreate(
                ['name' => 'impersonate tenants', 'guard_name' => $guard],
                [
                    'description' => 'Permite impersonar tenants para acceder a sus dominios',
                    'module' => 'tenants',
                    'is_active' => true
                ]
            );
            Permission::updateOrCreate(
                ['name' => 'manage profile', 'guard_name' => $guard],
                [
                    'description' => 'Permite gestionar el perfil personal',
                    'module' => 'profile',
                    'is_active' => true
                ]
            );
            Permission::updateOrCreate(
                ['name' => 'manage payment methods', 'guard_name' => $guard],
                [
                    'description' => 'Permite gestionar métodos de pago y configuraciones de facturación',
                    'module' => 'billing',
                    'is_active' => true
                ]
            );
            Permission::updateOrCreate(
                ['name' => 'manage subscriptions', 'guard_name' => $guard],
                [
                    'description' => 'Permite gestionar suscripciones de tenants',
                    'module' => 'billing',
                    'is_active' => true
                ]
            );
            Permission::updateOrCreate(
                ['name' => 'view activity logs', 'guard_name' => $guard],
                [
                    'description' => 'Permite ver registros de actividad del sistema',
                    'module' => 'system',
                    'is_active' => true
                ]
            );
            Permission::updateOrCreate(
                ['name' => 'manage settings', 'guard_name' => $guard],
                [
                    'description' => 'Permite gestionar configuraciones globales del sistema',
                    'module' => 'system',
                    'is_active' => true
                ]
            );

            // Create roles for this guard
            $superAdmin = Role::updateOrCreate(
                ['name' => 'super-admin', 'guard_name' => $guard],
                [
                    'description' => 'Administrador con acceso completo a todas las funciones',
                    'is_active' => true
                ]
            );
            $staff = Role::updateOrCreate(
                ['name' => 'staff', 'guard_name' => $guard],
                [
                    'description' => 'Personal administrativo con permisos limitados',
                    'is_active' => true
                ]
            );

            // Assign permissions to roles
            $superAdmin->givePermissionTo([
                'manage tenants',
                'manage staff',
                'manage plans',
                'manage payment methods',
                'manage subscriptions',
                'view activity logs',
                'manage settings',
                'impersonate tenants',
                'manage profile'
            ]);

            $staff->givePermissionTo([
                'manage tenants',
                'manage plans',
                'manage profile'
            ]);
        }
    }
}
