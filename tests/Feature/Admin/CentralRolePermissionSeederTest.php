<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Database\Seeders\CentralRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CentralRolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CentralRolePermissionSeeder::class);
    }

    /**
     * ✅ Test: The seeder creates the full granular catalog for the admin guard.
     */
    public function test_seeder_creates_full_granular_catalog(): void
    {
        $catalog = [
            // Tenants
            'view tenants', 'create tenants', 'edit tenants', 'delete tenants', 'restore tenants', 'impersonate tenants',
            // Staff
            'view staff', 'create staff', 'edit staff', 'delete staff',
            // Roles
            'view roles', 'create roles', 'edit roles', 'delete roles',
            // Permissions
            'view permissions', 'create permissions', 'edit permissions', 'delete permissions',
            // Plans
            'view plans', 'create plans', 'edit plans', 'delete plans', 'manage feature flags',
            // Billing
            'view subscriptions', 'manage subscription payments',
            'view payment methods', 'create payment methods', 'edit payment methods', 'delete payment methods',
            // System
            'view settings', 'edit settings', 'view activity logs',
            // Profile
            'manage profile',
        ];

        $adminPermissions = Permission::where('guard_name', 'admin')->pluck('name')->all();

        sort($catalog);
        sort($adminPermissions);

        $this->assertSame($catalog, $adminPermissions);
    }

    /**
     * ✅ Test: Deprecated coarse-grained permissions are removed.
     */
    public function test_deprecated_permissions_are_removed(): void
    {
        $deprecated = [
            'manage tenants',
            'manage staff',
            'manage plans',
            'manage payment methods',
            'manage subscriptions',
            'manage settings',
        ];

        foreach ($deprecated as $name) {
            $this->assertNull(
                Permission::where('guard_name', 'admin')->where('name', $name)->first(),
                "Deprecated permission '{$name}' should have been removed"
            );
        }
    }

    /**
     * ✅ Test: super-admin receives every active admin permission.
     */
    public function test_super_admin_has_all_active_permissions(): void
    {
        $superAdmin = Role::findByName('super-admin', 'admin');
        $active = Permission::where('guard_name', 'admin')->where('is_active', true)->count();

        $this->assertSame($active, $superAdmin->permissions()->count());
    }

    /**
     * ✅ Test: staff role is read-only with no mutating permissions.
     */
    public function test_staff_role_is_read_only(): void
    {
        $staff = Role::findByName('staff', 'admin');
        $names = $staff->permissions()->pluck('name')->all();
        sort($names);

        $this->assertSame([
            'manage profile',
            'view activity logs',
            'view payment methods',
            'view plans',
            'view subscriptions',
            'view tenants',
        ], $names);
    }
}
