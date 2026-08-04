<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use Database\Seeders\CentralRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionConsoleCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CentralRolePermissionSeeder::class);
    }

    public function test_assign_super_admin_role_to_existing_user(): void
    {
        $admin = AdminUser::factory()->create();

        $this->artisan('admin:assign-super-admin', ['email' => $admin->email])
            ->expectsOutput("Super-admin role assigned to {$admin->name} ({$admin->email})")
            ->assertExitCode(0);

        $this->assertTrue($admin->fresh()->hasRole('super-admin'));
    }

    public function test_assign_super_admin_role_to_missing_user_fails(): void
    {
        $this->artisan('admin:assign-super-admin', ['email' => 'missing@example.com'])
            ->expectsOutput('Admin user with email missing@example.com not found.')
            ->assertExitCode(1);
    }

    public function test_assign_staff_role_to_existing_user(): void
    {
        $admin = AdminUser::factory()->create();

        $this->artisan('admin:assign-staff', ['email' => $admin->email])
            ->expectsOutput("Staff role assigned to {$admin->name} ({$admin->email})")
            ->assertExitCode(0);

        $this->assertTrue($admin->fresh()->hasRole('staff'));
    }

    public function test_assign_staff_role_to_missing_user_fails(): void
    {
        $this->artisan('admin:assign-staff', ['email' => 'missing@example.com'])
            ->expectsOutput('Admin user with email missing@example.com not found.')
            ->assertExitCode(1);
    }
}
