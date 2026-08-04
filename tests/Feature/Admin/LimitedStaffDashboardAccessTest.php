<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LimitedStaffDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an admin user with a limited role that does NOT include
     * any tenant-management permission (the scenario that used to produce
     * a 403 when landing on the dashboard).
     */
    private function createLimitedStaff(): AdminUser
    {
        $role = Role::firstOrCreate([
            'name' => 'limited-staff',
            'guard_name' => 'admin',
        ]);

        $permission = Permission::firstOrCreate([
            'name' => 'manage profile',
            'guard_name' => 'admin',
        ]);

        $role->givePermissionTo($permission);

        $staff = AdminUser::factory()->create(['is_active' => true]);
        $staff->assignRole($role);

        return $staff;
    }

    public function test_limited_staff_can_load_dashboard_page(): void
    {
        $staff = $this->createLimitedStaff();
        $this->actingAs($staff, 'admin');

        $this->get('/admin/dashboard')->assertOk();
    }

    public function test_limited_staff_user_endpoint_returns_permissions_without_tenant_access(): void
    {
        $staff = $this->createLimitedStaff();
        $this->actingAs($staff, 'admin');

        $response = $this->getJson('/admin/user');

        $response->assertOk()
            ->assertJsonStructure(['user', 'permissions'])
            ->assertJsonPath('user.email', $staff->email);

        $permissions = $response->json('permissions');

        $this->assertContains('manage profile', $permissions);
        $this->assertNotContains('view tenants', $permissions);
    }

    public function test_limited_staff_cannot_fetch_dashboard_stats(): void
    {
        $staff = $this->createLimitedStaff();
        $this->actingAs($staff, 'admin');

        $this->getJson('/admin/api/dashboard-stats')->assertStatus(403);
    }
}
