<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\CentralRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionEdgeCasesTest extends TestCase
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

    private function authenticateAsStaff(): void
    {
        $admin = AdminUser::factory()->create();
        $admin->assignRole('staff');
        $this->actingAs($admin, 'admin');
    }

    public function test_cannot_delete_permission_assigned_to_roles(): void
    {
        $this->authenticateAsSuperAdmin();
        $permId = Permission::where('name', 'manage profile')->value('id');

        $response = $this->deleteJson("/admin/api/permissions/{$permId}");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Cannot delete permission assigned to roles']);
    }

    public function test_cannot_create_duplicate_permission(): void
    {
        $this->authenticateAsSuperAdmin();

        $response = $this->postJson('/admin/api/permissions', [
            'name' => 'manage tenants',
            'module' => 'tenants',
        ]);

        $response->assertStatus(422);
    }

    public function test_create_permission_without_module_fails(): void
    {
        $this->authenticateAsSuperAdmin();

        $response = $this->postJson('/admin/api/permissions', [
            'name' => 'orphan-permission',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['module']);
    }

    public function test_delete_permission_frees_name_for_reuse(): void
    {
        $this->authenticateAsSuperAdmin();

        $perm = Permission::create([
            'name' => 'temporary-permission',
            'guard_name' => 'admin',
            'module' => 'testing',
        ]);

        $this->deleteJson("/admin/api/permissions/{$perm->id}")->assertStatus(204);

        $response = $this->postJson('/admin/api/permissions', [
            'name' => 'temporary-permission',
            'module' => 'testing',
        ]);

        $response->assertStatus(201);
    }

    public function test_staff_cannot_create_permissions(): void
    {
        $this->authenticateAsStaff();

        $response = $this->postJson('/admin/api/permissions', [
            'name' => 'staff-permission',
            'module' => 'staff',
        ]);

        $response->assertStatus(403);
    }

    public function test_staff_cannot_manage_roles(): void
    {
        $this->authenticateAsStaff();

        $response = $this->postJson('/admin/api/roles', [
            'name' => 'staff-role',
        ]);

        $response->assertStatus(403);
    }

    public function test_staff_cannot_manage_staff(): void
    {
        $this->authenticateAsStaff();

        $response = $this->postJson('/admin/api/staff', [
            'name' => 'New Staff',
            'email' => 'new@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_self_deactivate(): void
    {
        $this->authenticateAsSuperAdmin();
        $adminId = auth('admin')->id();

        $response = $this->patchJson("/admin/api/staff/{$adminId}/toggle-status");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Cannot deactivate your own account']);
    }

    public function test_cannot_self_delete(): void
    {
        $this->authenticateAsSuperAdmin();
        $adminId = auth('admin')->id();

        $response = $this->deleteJson("/admin/api/staff/{$adminId}");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Cannot delete your own account']);
    }

    public function test_inactive_permission_is_listed_but_marked_inactive(): void
    {
        $this->authenticateAsSuperAdmin();

        $perm = Permission::where('guard_name', 'admin')->first();
        $perm->update(['is_active' => false]);

        $response = $this->getJson('/admin/api/permissions');

        $response->assertStatus(200);
        $modules = $response->json('permissions');
        $allPermissions = collect($modules)->flatten(1);

        $found = $allPermissions->firstWhere('id', $perm->id);
        $this->assertNotNull($found);
        $this->assertFalse($found['is_active']);
    }

    public function test_cannot_assign_role_with_higher_permissions_than_current_user(): void
    {
        $this->authenticateAsStaff();

        $staff = AdminUser::factory()->create();
        $staffRole = Role::where('name', 'staff')->first();

        $response = $this->postJson("/admin/api/staff/{$staff->id}/roles", [
            'role_ids' => [$staffRole->id],
        ]);

        $response->assertStatus(403);
    }

    public function test_create_role_with_empty_permissions_array_succeeds(): void
    {
        $this->authenticateAsSuperAdmin();

        $response = $this->postJson('/admin/api/roles', [
            'name' => 'empty-role',
            'permissions' => [],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('roles', ['name' => 'empty-role']);
    }

    public function test_update_role_with_null_permissions_does_not_change_permissions(): void
    {
        $this->authenticateAsSuperAdmin();
        $role = Role::create([
            'name' => 'custom-role',
            'guard_name' => 'admin',
            'is_active' => true,
        ]);
        $role->syncPermissions(
            Permission::where('guard_name', 'admin')
                ->whereIn('name', ['manage tenants', 'manage staff'])
                ->pluck('id')
        );
        $originalPerms = $role->permissions()->pluck('id')->sort()->values()->toArray();

        $response = $this->putJson("/admin/api/roles/{$role->id}", [
            'name' => 'custom-role',
        ]);

        $response->assertStatus(200);
        $currentPerms = $role->fresh()->permissions()->pluck('id')->sort()->values()->toArray();
        $this->assertEquals($originalPerms, $currentPerms);
    }
}
