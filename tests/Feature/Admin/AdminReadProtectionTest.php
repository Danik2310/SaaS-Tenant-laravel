<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Role;
use Database\Seeders\CentralRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lower-risk read/dropdown and shell endpoints that lacked a negative test.
 */
class AdminReadProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CentralRolePermissionSeeder::class);
    }

    private function actingAsWithPermissions(array $permissionNames): AdminUser
    {
        $role = Role::create(['name' => 'viewer-'.uniqid(), 'guard_name' => 'admin', 'is_active' => true]);
        $role->syncPermissions($permissionNames);

        $admin = AdminUser::factory()->create();
        $admin->assignRole($role);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_tenants_list_requires_view_tenants(): void
    {
        $this->actingAsWithPermissions(['view staff']);
        $this->getJson('/admin/api/tenants-list')->assertForbidden();

        $this->actingAsWithPermissions(['view tenants']);
        $this->getJson('/admin/api/tenants-list')->assertOk();
    }

    public function test_plans_list_requires_a_relevant_permission(): void
    {
        $this->actingAsWithPermissions(['view staff']);
        $this->getJson('/admin/api/plans-list')->assertForbidden();

        $this->actingAsWithPermissions(['view plans']);
        $this->getJson('/admin/api/plans-list')->assertOk();
    }

    public function test_staff_dropdown_endpoints_require_view_staff(): void
    {
        $this->actingAsWithPermissions(['view tenants']);
        $this->getJson('/admin/api/staff/get-roles')->assertForbidden();
        $this->getJson('/admin/api/staff/get-permissions')->assertForbidden();

        $this->actingAsWithPermissions(['view staff']);
        $this->getJson('/admin/api/staff/get-roles')->assertOk();
        $this->getJson('/admin/api/staff/get-permissions')->assertOk();
    }

    public function test_dashboard_redirects_guest_but_serves_admin(): void
    {
        $this->get('/admin/dashboard')->assertRedirect();

        $admin = AdminUser::factory()->create();
        $this->actingAs($admin, 'admin');
        $this->get('/admin/dashboard')->assertOk();
    }

    public function test_profile_endpoints_reject_guest_but_allow_admin(): void
    {
        $this->getJson('/admin/api/profile')->assertUnauthorized();

        $admin = AdminUser::factory()->create();
        $this->actingAs($admin, 'admin');
        $this->getJson('/admin/api/profile')->assertOk();
    }
}
