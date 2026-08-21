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

    public function test_delete_account_allows_when_other_admins_inactive(): void
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

        $response->assertStatus(200);
        $this->assertSoftDeleted('admin_users', ['id' => $admin->id]);
    }
}
