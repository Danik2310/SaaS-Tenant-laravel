<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();
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
                        'permissions',
                    ],
                ],
                'total',
            ])
            ->assertJsonCount(AdminUser::count(), 'staff');
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
            'is_active' => true,
        ];

        $response = $this->postJson('/admin/api/staff', $staffData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'staff' => [
                    'id',
                    'name',
                    'email',
                    'is_active',
                ],
                'message',
            ]);

        $this->assertDatabaseHas('admin_users', [
            'name' => 'New Staff',
            'email' => 'newstaff@example.com',
            'is_active' => true,
        ]);
    }

    /**
     * 👥 Test: Can update staff member
     */
    public function test_can_update_staff_member()
    {
        $staff = AdminUser::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'is_active' => false,
        ];

        $response = $this->putJson("/admin/api/staff/{$staff->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Staff member updated successfully']);

        $this->assertDatabaseHas('admin_users', [
            'id' => $staff->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'is_active' => false,
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
            'role_ids' => [$role->id],
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
        $response->assertStatus(204);

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
            'password' => '123',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'name',
                    'email',
                    'password',
                ],
            ]);
    }

    /**
     * 👥 Test: Returns 404 for non-existent staff member
     */
    public function test_returns_404_for_nonexistent_staff()
    {
        $this->getJson('/admin/api/staff/99999')->assertStatus(404);
        $this->putJson('/admin/api/staff/99999', [
            'name' => 'Ghost', 'email' => 'ghost@example.com',
        ])->assertStatus(404);
        $this->deleteJson('/admin/api/staff/99999')->assertStatus(404);
    }

    /**
     * 👥 Test: Users without permission cannot manage staff
     */
    public function test_unauthorized_user_cannot_manage_staff()
    {
        $admin = AdminUser::factory()->create();
        $this->actingAs($admin, 'admin');

        $this->getJson('/admin/api/staff')->assertStatus(403);
        $this->postJson('/admin/api/staff', [
            'name' => 'Test', 'email' => 'test@example.com', 'password' => 'Password123!',
        ])->assertStatus(403);
        $this->deleteJson('/admin/api/staff/1')->assertStatus(403);
    }

    /**
     * 👥 Test: Guest is redirected to login
     */
    public function test_guest_cannot_access_staff()
    {
        $this->getJson('/admin/api/staff')->assertStatus(401);
    }
}
