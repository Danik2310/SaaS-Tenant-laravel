<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\CentralRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionGuardIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CentralRolePermissionSeeder::class);
    }

    private function authenticateAsSuperAdmin(): void
    {
        $admin = AdminUser::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin, 'admin');
    }

    public function test_index_roles_only_returns_admin_guard_roles(): void
    {
        Role::create(['name' => 'web-role', 'guard_name' => 'web']);
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/admin/api/roles');

        $response->assertStatus(200);
        $roleNames = collect($response->json('roles'))->pluck('name');

        $this->assertContains('super-admin', $roleNames);
        $this->assertContains('staff', $roleNames);
        $this->assertNotContains('web-role', $roleNames);
    }

    public function test_index_permissions_only_returns_admin_guard_permissions(): void
    {
        Permission::create([
            'name' => 'web-permission',
            'guard_name' => 'web',
            'module' => 'web',
        ]);
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/admin/api/permissions');

        $response->assertStatus(200);
        $modules = $response->json('permissions');
        $allPermissions = collect($modules)->flatten(1);

        $names = $allPermissions->pluck('name');
        $this->assertContains('manage tenants', $names);
        $this->assertNotContains('web-permission', $names);
    }

    public function test_create_role_hardcodes_admin_guard(): void
    {
        $this->authenticateAsSuperAdmin();

        $response = $this->postJson('/admin/api/roles', [
            'name' => 'custom-role',
            'guard_name' => 'web',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('roles', [
            'name' => 'custom-role',
            'guard_name' => 'admin',
        ]);
    }

    public function test_update_role_only_finds_admin_guard_roles(): void
    {
        Role::create(['name' => 'web-role', 'guard_name' => 'web']);
        $this->authenticateAsSuperAdmin();

        $webRole = Role::where('guard_name', 'web')->first();

        $response = $this->putJson("/admin/api/roles/{$webRole->id}", [
            'name' => 'renamed-web-role',
        ]);

        $response->assertStatus(404);
    }

    public function test_delete_role_only_finds_admin_guard_roles(): void
    {
        Role::create(['name' => 'web-role', 'guard_name' => 'web']);
        $this->authenticateAsSuperAdmin();

        $webRole = Role::where('guard_name', 'web')->first();

        $response = $this->deleteJson("/admin/api/roles/{$webRole->id}");

        $response->assertStatus(404);
    }

    public function test_staff_get_roles_only_returns_admin_roles(): void
    {
        Role::create(['name' => 'web-role', 'guard_name' => 'web']);
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/admin/api/staff/get-roles');

        $response->assertStatus(200);
        $roleNames = collect($response->json('roles'))->pluck('name');

        $this->assertContains('super-admin', $roleNames);
        $this->assertNotContains('web-role', $roleNames);
    }

    public function test_staff_get_permissions_only_returns_admin_permissions(): void
    {
        Permission::create([
            'name' => 'web-permission',
            'guard_name' => 'web',
            'module' => 'web',
        ]);
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/admin/api/staff/get-permissions');

        $response->assertStatus(200);
        $names = collect($response->json('permissions'))->pluck('name');

        $this->assertContains('manage tenants', $names);
        $this->assertNotContains('web-permission', $names);
    }

    public function test_assign_roles_only_assigns_admin_guard_roles(): void
    {
        $this->authenticateAsSuperAdmin();
        $staff = AdminUser::factory()->create();
        Role::create(['name' => 'web-role', 'guard_name' => 'web']);
        $webRole = Role::where('guard_name', 'web')->first();

        $response = $this->postJson("/admin/api/staff/{$staff->id}/roles", [
            'role_ids' => [$webRole->id],
        ]);

        $response->assertStatus(200);
        $this->assertFalse($staff->fresh()->hasRole('web-role'));
    }

    public function test_create_permission_hardcodes_admin_guard(): void
    {
        $this->authenticateAsSuperAdmin();

        $response = $this->postJson('/admin/api/permissions', [
            'name' => 'custom-permission',
            'module' => 'custom',
            'guard_name' => 'web',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('permissions', [
            'name' => 'custom-permission',
            'guard_name' => 'admin',
        ]);
    }

    public function test_update_permission_only_finds_admin_guard(): void
    {
        Permission::create([
            'name' => 'web-permission',
            'guard_name' => 'web',
            'module' => 'web',
        ]);
        $this->authenticateAsSuperAdmin();
        $webPerm = Permission::where('guard_name', 'web')->first();

        $response = $this->putJson("/admin/api/permissions/{$webPerm->id}", [
            'name' => 'renamed-web-permission',
            'module' => 'web',
        ]);

        $response->assertStatus(404);
    }

    public function test_delete_permission_only_finds_admin_guard(): void
    {
        Permission::create([
            'name' => 'web-permission',
            'guard_name' => 'web',
            'module' => 'web',
        ]);
        $this->authenticateAsSuperAdmin();
        $webPerm = Permission::where('guard_name', 'web')->first();

        $response = $this->deleteJson("/admin/api/permissions/{$webPerm->id}");

        $response->assertStatus(404);
    }
}
