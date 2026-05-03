<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the super-admin role if it doesn't exist
        $role = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'admin'
        ]);

        // Create permissions if they don't exist
        $permissions = [
            'manage tenants',
            'manage staff',
            'manage plans',
            'impersonate tenants',
            'manage profile'
        ];

        foreach ($permissions as $permissionName) {
            $permission = \Spatie\Permission\Models\Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'admin'
            ]);
            $role->givePermissionTo($permission);
        }

        // Create admin user with permissions
        $admin = AdminUser::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin, 'admin');
    }

    /**
     * 👥 Test: Can list all staff members
     */
    public function test_can_list_staff_members()
    {
        // Create test staff
        AdminUser::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        AdminUser::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $response = $this->getJson('/admin/api/staff');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'staff' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                            'is_active',
                            'roles',
                            'permissions_count',
                            'permissions'
                        ]
                    ],
                    'total'
                ])
                ->assertJsonCount(3, 'staff'); // Including the admin user
    }

    /**
     * 👥 Test: Can create new staff member
     */
    public function test_can_create_staff_member()
    {
        $staffData = [
            'name' => 'New Staff',
            'email' => 'newstaff@example.com',
            'password' => 'Password123!',
            'is_active' => true
        ];

        $response = $this->postJson('/admin/api/staff', $staffData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'staff' => [
                        'id',
                        'name',
                        'email',
                        'is_active'
                    ],
                    'message'
                ]);

        $this->assertDatabaseHas('admin_users', [
            'name' => 'New Staff',
            'email' => 'newstaff@example.com',
            'is_active' => true
        ]);
    }

    /**
     * 👥 Test: Can update staff member
     */
    public function test_can_update_staff_member()
    {
        $staff = AdminUser::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com'
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'is_active' => false
        ];

        $response = $this->putJson("/admin/api/staff/{$staff->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson(['message' => 'Staff member updated successfully']);

        $this->assertDatabaseHas('admin_users', [
            'id' => $staff->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'is_active' => false
        ]);
    }

    /**
     * 👥 Test: Can assign roles to staff member
     */
    public function test_can_assign_roles_to_staff()
    {
        $staff = AdminUser::factory()->create();
        $role = Role::create(['name' => 'test-role', 'guard_name' => 'admin']);

        $response = $this->postJson("/admin/api/staff/{$staff->id}/roles", [
            'role_ids' => [$role->id]
        ]);

        $response->assertStatus(200)
                ->assertJson(['message' => 'Roles assigned successfully']);

        $this->assertTrue($staff->fresh()->hasRole('test-role'));
    }

    /**
     * 👥 Test: Can toggle staff status
     */
    public function test_can_toggle_staff_status()
    {
        $staff = AdminUser::factory()->create(['is_active' => true]);

        $response = $this->patchJson("/admin/api/staff/{$staff->id}/toggle-status");

        $response->assertStatus(200)
                ->assertJson(['message' => 'Staff status updated successfully']);

        $this->assertFalse($staff->fresh()->is_active);
    }

    /**
     * 👥 Test: Can soft delete and restore staff
     */
    public function test_can_soft_delete_and_restore_staff()
    {
        $staff = AdminUser::factory()->create();

        // Soft delete
        $response = $this->deleteJson("/admin/api/staff/{$staff->id}");
        $response->assertStatus(200);

        $this->assertSoftDeleted('admin_users', ['id' => $staff->id]);

        // Restore
        $response = $this->patchJson("/admin/api/staff/{$staff->id}/restore");
        $response->assertStatus(200);

        $this->assertDatabaseHas('admin_users', ['id' => $staff->id, 'deleted_at' => null]);
    }

    /**
     * 👥 Test: Validation errors are returned properly
     */
    public function test_staff_creation_validation_errors()
    {
        $response = $this->postJson('/admin/api/staff', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123'
        ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'message',
                    'errors' => [
                        'name',
                        'email',
                        'password'
                    ]
                ]);
    }
}