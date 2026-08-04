<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\CentralRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPermissionAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CentralRolePermissionSeeder::class);
    }

    private function authenticateAsSuperAdmin(): AdminUser
    {
        $admin = AdminUser::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function authenticateAs(array $permissionNames): AdminUser
    {
        $role = Role::create([
            'name' => 'limited-'.uniqid(),
            'guard_name' => 'admin',
            'is_active' => true,
        ]);
        $role->syncPermissions($permissionNames);

        $admin = AdminUser::factory()->create();
        $admin->assignRole($role);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function permissionId(string $name): int
    {
        return (int) Permission::where('name', $name)
            ->where('guard_name', 'admin')
            ->value('id');
    }

    public function test_super_admin_can_assign_direct_permissions_to_staff(): void
    {
        $this->authenticateAsSuperAdmin();
        $staff = AdminUser::factory()->create();

        $response = $this->postJson("/admin/api/staff/{$staff->id}/permissions", [
            'permission_ids' => [$this->permissionId('view tenants'), $this->permissionId('view staff')],
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Permissions assigned successfully']);

        $this->assertTrue($staff->fresh()->hasDirectPermission('view tenants'));
        $this->assertTrue($staff->fresh()->hasDirectPermission('view staff'));
    }

    public function test_cannot_assign_inactive_permission_to_staff(): void
    {
        $this->authenticateAsSuperAdmin();
        $staff = AdminUser::factory()->create();

        $perm = Permission::where('name', 'manage profile')
            ->where('guard_name', 'admin')
            ->firstOrFail();
        $perm->update(['is_active' => false]);

        $response = $this->postJson("/admin/api/staff/{$staff->id}/permissions", [
            'permission_ids' => [$perm->id],
        ]);

        $response->assertStatus(422);
        $this->assertFalse($staff->fresh()->hasDirectPermission('manage profile'));
    }

    public function test_cannot_assign_permission_without_prerequisite(): void
    {
        $this->authenticateAsSuperAdmin();
        $staff = AdminUser::factory()->create();

        $response = $this->postJson("/admin/api/staff/{$staff->id}/permissions", [
            'permission_ids' => [$this->permissionId('impersonate tenants')],
        ]);

        $response->assertStatus(422);
        $this->assertFalse($staff->fresh()->hasDirectPermission('impersonate tenants'));
    }

    public function test_cannot_assign_permission_the_assigner_does_not_have(): void
    {
        $this->authenticateAs(['edit staff', 'view staff', 'view tenants']);
        $staff = AdminUser::factory()->create();

        $response = $this->postJson("/admin/api/staff/{$staff->id}/permissions", [
            'permission_ids' => [$this->permissionId('impersonate tenants')],
        ]);

        $response->assertStatus(403);
        $this->assertFalse($staff->fresh()->hasDirectPermission('impersonate tenants'));
    }

    public function test_assign_permissions_to_nonexistent_staff_returns_404(): void
    {
        $this->authenticateAsSuperAdmin();

        $response = $this->postJson('/admin/api/staff/999999/permissions', [
            'permission_ids' => [$this->permissionId('view tenants')],
        ]);

        $response->assertStatus(404);
    }

    public function test_assign_empty_permission_ids_fails(): void
    {
        $this->authenticateAsSuperAdmin();
        $staff = AdminUser::factory()->create();

        $response = $this->postJson("/admin/api/staff/{$staff->id}/permissions", [
            'permission_ids' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_user_loses_access_to_deactivated_permission(): void
    {
        $admin = $this->authenticateAs(['view tenants']);

        $this->assertTrue($admin->can('view tenants'));

        $permission = Permission::where('name', 'view tenants')
            ->where('guard_name', 'admin')
            ->firstOrFail();
        $permission->update(['is_active' => false]);

        $this->assertFalse($admin->fresh()->can('view tenants'));
    }

    public function test_route_middleware_rejects_deactivated_permission(): void
    {
        $this->authenticateAs(['view tenants']);

        $permission = Permission::where('name', 'view tenants')
            ->where('guard_name', 'admin')
            ->firstOrFail();
        $permission->update(['is_active' => false]);

        $this->getJson('/admin/api/tenants')->assertForbidden();
    }

    public function test_cannot_assign_role_containing_permissions_the_assigner_does_not_have(): void
    {
        $this->authenticateAs(['edit staff', 'view staff', 'view tenants']);
        $staff = AdminUser::factory()->create();

        $powerfulRole = Role::create([
            'name' => 'powerful-'.uniqid(),
            'guard_name' => 'admin',
            'is_active' => true,
        ]);
        $powerfulRole->syncPermissions([
            $this->permissionId('view tenants'),
            $this->permissionId('impersonate tenants'),
        ]);

        $response = $this->postJson("/admin/api/staff/{$staff->id}/roles", [
            'role_ids' => [$powerfulRole->id],
        ]);

        $response->assertStatus(403);
        $this->assertFalse($staff->fresh()->hasRole($powerfulRole));
    }
}
