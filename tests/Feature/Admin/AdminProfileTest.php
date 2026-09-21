<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_delete_account_requires_password(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
        $permission = Permission::firstOrCreate(['name' => 'manage profile', 'guard_name' => 'admin']);
        $role->givePermissionTo($permission);

        $admin = AdminUser::factory()->create(['password' => Hash::make('secret123')]);
        $admin->assignRole('admin');
        $this->actingAs($admin, 'admin');

        $response = $this->deleteJson('/admin/api/profile', [
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Password is incorrect');

        $this->assertDatabaseHas('admin_users', ['id' => $admin->id, 'deleted_at' => null]);
    }

    public function test_delete_account_blocks_last_active_admin(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
        $permission = Permission::firstOrCreate(['name' => 'manage profile', 'guard_name' => 'admin']);
        $role->givePermissionTo($permission);

        $admin = AdminUser::factory()->create(['password' => Hash::make('secret123')]);
        $admin->assignRole('admin');
        $this->actingAs($admin, 'admin');

        $response = $this->deleteJson('/admin/api/profile', [
            'password' => 'secret123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete the last active admin account');

        $this->assertDatabaseHas('admin_users', ['id' => $admin->id, 'deleted_at' => null]);
    }

    public function test_delete_account_allows_when_other_admins_exist(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
        $permission = Permission::firstOrCreate(['name' => 'manage profile', 'guard_name' => 'admin']);
        $role->givePermissionTo($permission);

        $admin = AdminUser::factory()->create(['password' => Hash::make('secret123')]);
        $admin->assignRole('admin');

        $otherAdmin = AdminUser::factory()->create(['is_active' => true]);

        $this->actingAs($admin, 'admin');

        $response = $this->deleteJson('/admin/api/profile', [
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Account deleted successfully');

        $this->assertSoftDeleted('admin_users', ['id' => $admin->id]);
    }

    public function test_delete_account_blocks_when_other_admins_inactive(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
        $permission = Permission::firstOrCreate(['name' => 'manage profile', 'guard_name' => 'admin']);
        $role->givePermissionTo($permission);

        $admin = AdminUser::factory()->create(['password' => Hash::make('secret123')]);
        $admin->assignRole('admin');

        AdminUser::factory()->inactive()->create();

        $this->actingAs($admin, 'admin');

        $response = $this->deleteJson('/admin/api/profile', [
            'password' => 'secret123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete the last active admin account');

        $this->assertDatabaseHas('admin_users', ['id' => $admin->id, 'deleted_at' => null]);
    }

    public function test_delete_account_blocks_main_admin(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
        $permission = Permission::firstOrCreate(['name' => 'manage profile', 'guard_name' => 'admin']);
        $role->givePermissionTo($permission);

        $admin = AdminUser::factory()->create([
            'password' => Hash::make('secret123'),
            'is_main_admin' => true,
        ]);
        $admin->assignRole('admin');
        $this->actingAs($admin, 'admin');

        $response = $this->deleteJson('/admin/api/profile', [
            'password' => 'secret123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', "The system's main administrator account cannot be deleted.");

        $this->assertDatabaseHas('admin_users', ['id' => $admin->id, 'deleted_at' => null]);
    }

    public function test_any_authenticated_admin_can_view_own_profile(): void
    {
        $admin = AdminUser::factory()->create(['is_active' => true]);
        $this->actingAs($admin, 'admin');

        $this->getJson('/admin/api/profile')
            ->assertOk()
            ->assertJsonStructure(['profile' => ['id', 'name', 'email', 'is_active', 'created_at']])
            ->assertJsonPath('profile.id', $admin->id)
            ->assertJsonPath('profile.name', $admin->name)
            ->assertJsonPath('profile.email', $admin->email);
    }

    public function test_any_authenticated_admin_can_update_own_profile(): void
    {
        $admin = AdminUser::factory()->create(['is_active' => true]);
        $this->actingAs($admin, 'admin');

        $this->putJson('/admin/api/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Profile updated successfully');

        $this->assertDatabaseHas('admin_users', [
            'id' => $admin->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_profile_update_rejects_other_users_email(): void
    {
        $admin = AdminUser::factory()->create(['is_active' => true]);
        $other = AdminUser::factory()->create(['email' => 'other@example.com']);
        $this->actingAs($admin, 'admin');

        $this->putJson('/admin/api/profile', [
            'name' => $admin->name,
            'email' => 'other@example.com',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_profile_update_allows_unchanged_email(): void
    {
        $admin = AdminUser::factory()->create(['is_active' => true]);
        $this->actingAs($admin, 'admin');

        $this->putJson('/admin/api/profile', [
            'name' => 'Kept Name',
            'email' => $admin->email,
        ])
            ->assertOk();

        $this->assertDatabaseHas('admin_users', ['id' => $admin->id, 'name' => 'Kept Name']);
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        $admin = AdminUser::factory()->create([
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->actingAs($admin, 'admin');

        $this->putJson('/admin/api/profile/password', [
            'current_password' => 'wrong-password',
            'new_password' => 'NewPassword123!',
            'new_password_confirmation' => 'NewPassword123!',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Current password is incorrect');
    }
}
