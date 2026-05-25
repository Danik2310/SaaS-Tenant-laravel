<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\CentralRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CentralRolePermissionSeeder::class);
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    private function authenticateAsSuperAdmin(): void
    {
        $admin = AdminUser::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin, 'admin');
    }

    private function createRole(string $name, string $description = ''): Role
    {
        return Role::create([
            'name' => $name,
            'description' => $description ?: "$name role description",
            'guard_name' => 'admin',
            'is_active' => true,
        ]);
    }

    // ──────────────────────────────────────────────
    //  Role CRUD — Happy path
    // ──────────────────────────────────────────────

    public function test_admin_can_create_role_without_permissions(): void
    {
        $this->authenticateAsSuperAdmin();

        $response = $this->postJson('/admin/api/roles', [
            'name' => 'editor',
            'description' => 'Editor role description',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Role created successfully',
            ])
            ->assertJsonStructure([
                'message',
                'role' => [
                    'id',
                    'name',
                    'description',
                    'is_active',
                    'permissions_count',
                    'permission_ids',
                ],
            ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'editor',
            'guard_name' => 'admin',
            'description' => 'Editor role description',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_role_with_permissions(): void
    {
        $this->authenticateAsSuperAdmin();

        // Use permissions without prerequisites to avoid dependency validation
        $permissionIds = Permission::where('guard_name', 'admin')
            ->whereIn('name', ['manage tenants', 'manage staff', 'manage plans'])
            ->pluck('id')
            ->toArray();

        $response = $this->postJson('/admin/api/roles', [
            'name' => 'moderator',
            'description' => 'Moderator role',
            'permissions' => $permissionIds,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Role created successfully',
            ]);

        $roleId = $response->json('role.id');

        // Assert the role has exactly the permissions we assigned
        $assignedPermissionIds = Permission::query()
            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_has_permissions.role_id', $roleId)
            ->pluck('permissions.id')
            ->sort()
            ->values()
            ->toArray();

        sort($permissionIds);

        $this->assertEquals($permissionIds, $assignedPermissionIds);
    }

    public function test_admin_can_update_role_permissions(): void
    {
        $this->authenticateAsSuperAdmin();

        // Create a role with initial permissions (safe set without prerequisites)
        $initialPermissionIds = Permission::where('guard_name', 'admin')
            ->whereIn('name', ['manage tenants', 'manage staff'])
            ->pluck('id')
            ->toArray();

        $role = $this->createRole('reviewer');
        $role->syncPermissions($initialPermissionIds);

        // Update the role with different permissions (safe set without prerequisites)
        $updatedPermissionIds = Permission::where('guard_name', 'admin')
            ->whereIn('name', ['manage tenants', 'manage staff', 'manage plans'])
            ->pluck('id')
            ->toArray();

        $response = $this->putJson("/admin/api/roles/{$role->id}", [
            'name' => 'reviewer',
            'permissions' => $updatedPermissionIds,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Role updated successfully',
            ]);

        // Assert the role now has the new set of permissions
        $currentPermissionIds = $role->refresh()->permissions->pluck('id')->sort()->values()->toArray();
        sort($updatedPermissionIds);

        $this->assertEquals($updatedPermissionIds, $currentPermissionIds);
    }

    public function test_admin_can_delete_role(): void
    {
        $this->authenticateAsSuperAdmin();

        $role = $this->createRole('temporary-role');

        $response = $this->deleteJson("/admin/api/roles/{$role->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    // ──────────────────────────────────────────────
    //  Role CRUD — Edge cases / Unauthorized
    // ──────────────────────────────────────────────

    public function test_cannot_delete_role_with_users(): void
    {
        $this->authenticateAsSuperAdmin();

        $role = $this->createRole('protected-role');

        $admin = AdminUser::factory()->create();
        $admin->assignRole($role->name);

        $response = $this->deleteJson("/admin/api/roles/{$role->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete role with assigned users',
            ]);

        // Role should still exist
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_guest_cannot_access_role_endpoints(): void
    {
        // All role/permission endpoints under /admin/api/* return a JSON error
        // for unauthenticated requests
        $this->get('/admin/api/roles')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);

        $this->post('/admin/api/roles', ['name' => 'guest-role'])
            ->assertUnauthorized();

        $this->put('/admin/api/roles/1', ['name' => 'nothing'])
            ->assertUnauthorized();

        $this->delete('/admin/api/roles/1')
            ->assertUnauthorized();

        $this->get('/admin/api/permissions')
            ->assertUnauthorized();
    }

    // ──────────────────────────────────────────────
    //  Permission listing
    // ──────────────────────────────────────────────

    public function test_admin_can_list_all_permissions(): void
    {
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/admin/api/permissions');

        $response->assertStatus(200);

        // Assert the response has a "permissions" object with module keys
        $response->assertJsonStructure([
            'permissions' => [
                'tenants',
                'staff',
                'plans',
                'billing',
                'profile',
                'system',
            ],
        ]);

        $permissions = $response->json('permissions');

        // Each module should have at least one permission
        foreach (['tenants', 'staff', 'plans', 'billing', 'profile', 'system'] as $module) {
            $this->assertNotEmpty($permissions[$module], "Module '$module' should have permissions");
        }

        // Each permission entry should have the expected structure
        foreach ($permissions['tenants'] as $permission) {
            $this->assertArrayHasKey('id', $permission);
            $this->assertArrayHasKey('name', $permission);
            $this->assertArrayHasKey('description', $permission);
            $this->assertArrayHasKey('module', $permission);
            $this->assertArrayHasKey('is_active', $permission);
        }
    }

    public function test_role_response_includes_permissions(): void
    {
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/admin/api/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'roles' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'is_active',
                        'permissions_count',
                        'permission_ids',
                    ],
                ],
            ]);

        $roles = $response->json('roles');

        // Each role should have a permission_ids array (IDs, not full objects)
        foreach ($roles as $role) {
            $this->assertIsArray($role['permission_ids'], "Role '{$role['name']}' should have a permission_ids array");

            // All entries in permission_ids should be integers
            foreach ($role['permission_ids'] as $permId) {
                $this->assertIsInt($permId, "Permission ID should be an integer, got: $permId");
            }
        }
    }

    public function test_role_response_includes_permissions_count(): void
    {
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/admin/api/roles');

        $response->assertStatus(200);

        $roles = $response->json('roles');

        foreach ($roles as $role) {
            $expectedCount = count($role['permission_ids']);
            $this->assertSame(
                $expectedCount,
                $role['permissions_count'],
                "Role '{$role['name']}' permissions_count ({$role['permissions_count']}) does not match actual permission count ({$expectedCount})"
            );
        }
    }

    // ──────────────────────────────────────────────
    //  Validation edge cases
    // ──────────────────────────────────────────────

    public function test_create_role_with_invalid_permission_id_fails(): void
    {
        $this->authenticateAsSuperAdmin();

        $response = $this->postJson('/admin/api/roles', [
            'name' => 'hacker-role',
            'description' => 'Trying to assign non-existent permissions',
            'permissions' => [99999],
        ]);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors(['permissions.0']);

        // Role should NOT have been created
        $this->assertDatabaseMissing('roles', ['name' => 'hacker-role']);
    }

    public function test_update_role_name_to_duplicate_fails(): void
    {
        $this->authenticateAsSuperAdmin();

        $roleOne = $this->createRole('editor');
        $roleTwo = $this->createRole('viewer');

        // Try to update viewer to have the same name as editor
        $response = $this->putJson("/admin/api/roles/{$roleTwo->id}", [
            'name' => 'editor',
            'description' => 'Viewer role',
        ]);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors(['name']);

        // Role two's name should remain unchanged
        $this->assertDatabaseHas('roles', [
            'id' => $roleTwo->id,
            'name' => 'viewer',
        ]);
    }

    // ──────────────────────────────────────────────
    //  Permission dependency validation
    // ──────────────────────────────────────────────

    public function test_cannot_create_role_with_dependent_permission_without_prerequisite(): void
    {
        $this->authenticateAsSuperAdmin();

        $impersonateId = Permission::where('name', 'impersonate tenants')
            ->where('guard_name', 'admin')
            ->value('id');

        $response = $this->postJson('/admin/api/roles', [
            'name' => 'support-agent',
            'description' => 'Support agent with impersonation only',
            'permissions' => [$impersonateId],
        ]);

        $response->assertStatus(422);

        // Role should NOT have been created
        $this->assertDatabaseMissing('roles', ['name' => 'support-agent']);
    }

    public function test_cannot_update_role_with_dependent_permission_without_prerequisite(): void
    {
        $this->authenticateAsSuperAdmin();

        $role = $this->createRole('support-agent');
        $role->syncPermissions(
            Permission::where('guard_name', 'admin')
                ->whereIn('name', ['manage tenants'])
                ->pluck('id')
        );

        $impersonateId = Permission::where('name', 'impersonate tenants')
            ->where('guard_name', 'admin')
            ->value('id');

        $response = $this->putJson("/admin/api/roles/{$role->id}", [
            'name' => 'support-agent',
            'permissions' => [$impersonateId],
        ]);

        $response->assertStatus(422);
    }
}
