<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Permission;
use App\Models\Role;
use App\Shared\Constants\PermissionNames;
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
     * 👥 Test: Updating a staff member without a roles key preserves existing roles
     */
    public function test_updating_staff_without_roles_preserves_existing_roles()
    {
        Role::create(['name' => 'support', 'guard_name' => 'admin']);
        $staff = AdminUser::factory()->create();
        $staff->assignRole('support');

        $response = $this->putJson("/admin/api/staff/{$staff->id}", [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'is_active' => true,
        ]);

        $response->assertStatus(200);

        $this->assertTrue($staff->fresh()->hasRole('support'));
    }

    /**
     * 👥 Test: Updating a staff member with an explicit empty roles array clears roles
     */
    public function test_updating_staff_with_empty_roles_clears_them()
    {
        Role::create(['name' => 'support', 'guard_name' => 'admin']);
        $staff = AdminUser::factory()->create();
        $staff->assignRole('support');

        $response = $this->putJson("/admin/api/staff/{$staff->id}", [
            'name' => $staff->name,
            'email' => $staff->email,
            'roles' => [],
            'is_active' => true,
        ]);

        $response->assertStatus(200);

        $this->assertFalse($staff->fresh()->hasRole('support'));
    }

    /**
     * 👥 Test: A staff email can be reused once the previous account is soft-deleted
     */
    public function test_can_recreate_staff_with_email_of_soft_deleted_staff()
    {
        $staff = AdminUser::factory()->create(['email' => 'recreate@example.com']);
        $staff->delete();

        $this->assertSoftDeleted('admin_users', ['id' => $staff->id]);

        $response = $this->postJson('/admin/api/staff', [
            'name' => 'Recreated',
            'email' => 'recreate@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        $response->assertStatus(201);
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
        auth('admin')->logout();
        $this->getJson('/admin/api/staff')->assertStatus(401);
    }

    /**
     * 🕹️ Test: A staff member can only be created with a single role
     */
    public function test_cannot_create_staff_with_multiple_roles()
    {
        $roleA = Role::create(['name' => 'role-a', 'guard_name' => 'admin']);
        $roleB = Role::create(['name' => 'role-b', 'guard_name' => 'admin']);

        $response = $this->postJson('/admin/api/staff', [
            'name' => 'Multiple',
            'email' => 'multiple@example.com',
            'password' => 'Password123!',
            'roles' => [$roleA->id, $roleB->id],
            'is_active' => true,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('roles');
    }

    /**
     * 🕹️ Test: A staff member can only be updated with a single role
     */
    public function test_cannot_update_staff_with_multiple_roles()
    {
        $roleA = Role::create(['name' => 'role-a', 'guard_name' => 'admin']);
        $roleB = Role::create(['name' => 'role-b', 'guard_name' => 'admin']);
        $staff = AdminUser::factory()->create();

        $response = $this->putJson("/admin/api/staff/{$staff->id}", [
            'name' => $staff->name,
            'email' => $staff->email,
            'roles' => [$roleA->id, $roleB->id],
            'is_active' => true,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('roles');
    }

    /**
     * 🕹️ Test: Roles endpoint rejects more than one role
     */
    public function test_cannot_assign_multiple_roles_via_roles_endpoint()
    {
        $roleA = Role::create(['name' => 'role-a', 'guard_name' => 'admin']);
        $roleB = Role::create(['name' => 'role-b', 'guard_name' => 'admin']);
        $staff = AdminUser::factory()->create();

        $response = $this->postJson("/admin/api/staff/{$staff->id}/roles", [
            'role_ids' => [$roleA->id, $roleB->id],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('role_ids');
    }

    /**
     * 🛡️ Test: A super-admin cannot change the roles of their own account (update endpoint)
     */
    public function test_super_admin_cannot_change_own_roles_via_update()
    {
        $basic = Role::create(['name' => 'basic', 'guard_name' => 'admin']);
        $me = auth('admin')->user();

        $response = $this->putJson("/admin/api/staff/{$me->id}", [
            'name' => $me->name,
            'email' => $me->email,
            'roles' => [$basic->id],
            'is_active' => true,
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'You cannot change the roles of your own Administrator account.']);

        $this->assertTrue($me->fresh()->hasRole('super-admin'));
    }

    /**
     * 🛡️ Test: A super-admin cannot change the roles of their own account (roles endpoint)
     */
    public function test_super_admin_cannot_change_own_roles_via_roles_endpoint()
    {
        $basic = Role::create(['name' => 'basic', 'guard_name' => 'admin']);
        $me = auth('admin')->user();

        $response = $this->postJson("/admin/api/staff/{$me->id}/roles", [
            'role_ids' => [$basic->id],
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'You cannot change the roles of your own Administrator account.']);

        $this->assertTrue($me->fresh()->hasRole('super-admin'));
    }

    /**
     * 🛡️ Test: The super-admin role cannot be removed from the last active super-admin
     */
    public function test_cannot_remove_super_admin_from_last_super_admin()
    {
        $basic = Role::create(['name' => 'basic', 'guard_name' => 'admin']);

        auth('admin')->user()->forceDelete();

        $target = AdminUser::factory()->create();
        $target->assignRole('super-admin');

        $manager = $this->createStaffManager();

        $response = $this->postJson("/admin/api/staff/{$target->id}/roles", [
            'role_ids' => [$basic->id],
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Cannot remove the super-admin role from the last administrator account.']);

        $this->assertTrue($target->fresh()->hasRole('super-admin'));
    }

    /**
     * 🛡️ Test: Another super-admin can be edited when more than one exists
     */
    public function test_can_edit_another_super_admin_when_multiple_exist()
    {
        $basic = Role::create(['name' => 'basic', 'guard_name' => 'admin']);
        $other = AdminUser::factory()->create();
        $other->assignRole('super-admin');

        $response = $this->postJson("/admin/api/staff/{$other->id}/roles", [
            'role_ids' => [$basic->id],
        ]);

        $response->assertStatus(200);

        $this->assertFalse($other->fresh()->hasRole('super-admin'));
        $this->assertTrue($other->fresh()->hasRole('basic'));
    }

    /**
     * 🔄 Test: Changing your own (non-super-admin) role ends the session and requires sign-in
     */
    public function test_self_role_change_triggers_session_reset()
    {
        $basic = Role::create(['name' => 'basic', 'guard_name' => 'admin']);
        $manager = $this->createStaffManager();

        $response = $this->putJson("/admin/api/staff/{$manager->id}", [
            'name' => $manager->name,
            'email' => $manager->email,
            'roles' => [$basic->id],
            'is_active' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson(['relogin_required' => true]);

        $this->assertTrue(auth('admin')->guest());
        $this->assertTrue($manager->fresh()->hasRole('basic'));

        $clearedCookie = collect($response->headers->getCookies())
            ->contains(fn ($cookie) => $cookie->isCleared());
        $this->assertTrue($clearedCookie);
    }

    /**
     * 🔄 Test: Changing your own role via the roles endpoint also ends the session
     */
    public function test_self_role_change_via_roles_endpoint_triggers_session_reset()
    {
        $basic = Role::create(['name' => 'basic', 'guard_name' => 'admin']);
        $manager = $this->createStaffManager();

        $response = $this->postJson("/admin/api/staff/{$manager->id}/roles", [
            'role_ids' => [$basic->id],
        ]);

        $response->assertStatus(200)
            ->assertJson(['relogin_required' => true]);

        $this->assertTrue(auth('admin')->guest());
    }

    /**
     * 🔄 Test: Submitting the same role on your own account does not end the session
     */
    public function test_self_role_change_with_unchanged_role_does_not_end_session()
    {
        $manager = $this->createStaffManager();
        $managerRole = Role::where('name', 'staff-manager')->where('guard_name', 'admin')->first();

        $response = $this->putJson("/admin/api/staff/{$manager->id}", [
            'name' => $manager->name,
            'email' => $manager->email,
            'roles' => [$managerRole->id],
            'is_active' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonMissing(['relogin_required' => true]);

        $this->assertTrue(auth('admin')->check());
    }

    /**
     * 🔄 Test: Changing another staff member's role does not end the session
     */
    public function test_non_self_role_change_does_not_end_session()
    {
        $basic = Role::create(['name' => 'basic', 'guard_name' => 'admin']);
        $manager = $this->createStaffManager();
        $other = AdminUser::factory()->create();
        $other->assignRole('staff-manager');

        $response = $this->postJson("/admin/api/staff/{$other->id}/roles", [
            'role_ids' => [$basic->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonMissing(['relogin_required' => true]);

        $this->assertTrue(auth('admin')->check());
        $this->assertSame($manager->id, auth('admin')->id());
    }

    /**
     * 🚩 Test: The show endpoint exposes is_self and is_last_super_admin flags
     */
    public function test_show_returns_self_and_last_super_admin_flags()
    {
        $admin = auth('admin')->user();

        $selfResponse = $this->getJson("/admin/api/staff/{$admin->id}");
        $selfResponse->assertStatus(200)
            ->assertJsonFragment(['is_self' => true])
            ->assertJsonFragment(['is_last_super_admin' => true]);

        $staff = AdminUser::factory()->create();
        $otherResponse = $this->getJson("/admin/api/staff/{$staff->id}");
        $otherResponse->assertStatus(200)
            ->assertJsonFragment(['is_self' => false])
            ->assertJsonFragment(['is_last_super_admin' => false]);
    }

    /**
     * 🔒 Test: The main administrator cannot be deleted by another administrator
     */
    public function test_another_admin_cannot_delete_main_admin()
    {
        $mainAdmin = $this->createMainAdmin();

        $response = $this->deleteJson("/admin/api/staff/{$mainAdmin->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', "The system's main administrator account cannot be modified.");

        $this->assertDatabaseHas('admin_users', ['id' => $mainAdmin->id, 'deleted_at' => null]);
    }

    /**
     * 🔒 Test: The main administrator cannot be deactivated by another administrator
     */
    public function test_another_admin_cannot_toggle_main_admin_status()
    {
        $mainAdmin = $this->createMainAdmin();

        $response = $this->patchJson("/admin/api/staff/{$mainAdmin->id}/toggle-status");

        $response->assertStatus(422);
        $this->assertTrue($mainAdmin->fresh()->is_active);
    }

    /**
     * 🔒 Test: The main administrator cannot be updated by another administrator
     */
    public function test_another_admin_cannot_update_main_admin()
    {
        $mainAdmin = $this->createMainAdmin();

        $response = $this->putJson("/admin/api/staff/{$mainAdmin->id}", [
            'name' => 'Hacked Name',
            'email' => 'hacked@example.com',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseMissing('admin_users', [
            'id' => $mainAdmin->id,
            'name' => 'Hacked Name',
        ]);
        $this->assertSame($mainAdmin->email, $mainAdmin->fresh()->email);
    }

    /**
     * 🔒 Test: The main administrator's roles cannot be changed by another administrator
     */
    public function test_another_admin_cannot_assign_roles_to_main_admin()
    {
        $mainAdmin = $this->createMainAdmin();
        $basic = Role::create(['name' => 'basic', 'guard_name' => 'admin']);

        $response = $this->postJson("/admin/api/staff/{$mainAdmin->id}/roles", [
            'role_ids' => [$basic->id],
        ]);

        $response->assertStatus(422);
        $this->assertTrue($mainAdmin->fresh()->hasRole('super-admin'));
    }

    /**
     * 🔒 Test: The main administrator's permissions cannot be changed by another administrator
     */
    public function test_another_admin_cannot_assign_permissions_to_main_admin()
    {
        $mainAdmin = $this->createMainAdmin();
        $permission = Permission::firstOrCreate(['name' => 'view tenants', 'guard_name' => 'admin']);

        $response = $this->postJson("/admin/api/staff/{$mainAdmin->id}/permissions", [
            'permission_ids' => [$permission->id],
        ]);

        $response->assertStatus(422);
        $this->assertCount(0, $mainAdmin->fresh()->permissions);
    }

    /**
     * 🔓 Test: The main administrator can still edit their own account
     */
    public function test_main_admin_can_update_own_account()
    {
        $mainAdmin = $this->createMainAdmin();
        $this->actingAs($mainAdmin, 'admin');

        $response = $this->putJson("/admin/api/staff/{$mainAdmin->id}", [
            'name' => 'Renamed Main Admin',
            'email' => $mainAdmin->email,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('admin_users', [
            'id' => $mainAdmin->id,
            'name' => 'Renamed Main Admin',
        ]);
    }

    /**
     * 🔒 Test: Staff endpoints expose the is_main_admin flag
     */
    public function test_staff_endpoints_expose_is_main_admin_flag()
    {
        $mainAdmin = $this->createMainAdmin();
        $other = AdminUser::factory()->create();

        $this->getJson('/admin/api/staff')
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $mainAdmin->id, 'is_main_admin' => true])
            ->assertJsonFragment(['id' => $other->id, 'is_main_admin' => false]);

        $this->getJson("/admin/api/staff/{$mainAdmin->id}")
            ->assertStatus(200)
            ->assertJsonPath('staff.is_main_admin', true);
    }

    private function createMainAdmin(): AdminUser
    {
        $admin = AdminUser::factory()->create(['is_main_admin' => true]);
        $admin->assignRole('super-admin');

        return $admin;
    }

    private function createStaffManager(): AdminUser
    {
        $role = Role::firstOrCreate([
            'name' => 'staff-manager',
            'guard_name' => 'admin',
        ]);
        $role->givePermissionTo(Permission::firstOrCreate([
            'name' => PermissionNames::EDIT_STAFF,
            'guard_name' => 'admin',
        ]));

        $manager = AdminUser::factory()->create();
        $manager->assignRole('staff-manager');
        $this->actingAs($manager, 'admin');

        return $manager;
    }
}
