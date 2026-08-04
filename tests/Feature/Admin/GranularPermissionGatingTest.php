<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Role;
use Database\Seeders\CentralRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GranularPermissionGatingTest extends TestCase
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

    public function test_view_only_tenants_role_can_list_but_not_mutate(): void
    {
        $this->actingAsWithPermissions(['view tenants']);

        $this->getJson('/admin/api/tenants')->assertOk();
        $this->getJson('/admin/api/tenants-list')->assertOk();

        $this->postJson('/admin/api/tenants', [
            'name' => 'Blocked',
            'email' => 'blocked@example.com',
            'domain' => 'blocked.example.com',
        ])->assertForbidden();

        $this->putJson('/admin/api/tenants/1', ['name' => 'Blocked'])->assertForbidden();
        $this->deleteJson('/admin/api/tenants/1')->assertForbidden();
    }

    public function test_edit_tenants_does_not_imply_delete(): void
    {
        $this->actingAsWithPermissions(['view tenants', 'edit tenants']);

        $this->putJson('/admin/api/tenants/1', ['name' => 'Allowed'])->assertStatus(404);
        $this->deleteJson('/admin/api/tenants/1')->assertForbidden();
    }

    public function test_view_only_staff_role_can_list_but_not_mutate(): void
    {
        $this->actingAsWithPermissions(['view staff']);

        $this->getJson('/admin/api/staff')->assertOk();

        $this->postJson('/admin/api/staff', [
            'name' => 'Blocked',
            'email' => 'blocked@example.com',
            'password' => 'Password123!',
        ])->assertForbidden();

        $this->deleteJson('/admin/api/staff/1')->assertForbidden();
    }

    public function test_view_only_plans_role_can_list_but_not_mutate(): void
    {
        $this->actingAsWithPermissions(['view plans']);

        $this->getJson('/admin/api/plans')->assertOk();
        $this->getJson('/admin/api/plans-list')->assertOk();

        $this->postJson('/admin/api/plans', [
            'name' => 'Blocked',
            'slug' => 'blocked',
            'price' => 10,
        ])->assertForbidden();

        $this->deleteJson('/admin/api/plans/1')->assertForbidden();
    }

    public function test_subscription_payments_require_manage_subscription_payments(): void
    {
        $this->actingAsWithPermissions(['view subscriptions']);

        $this->postJson('/admin/api/subscriptions/1/payments', [
            'amount' => 100,
            'method' => 'stripe',
            'status' => 'completed',
        ])->assertForbidden();
    }

    public function test_feature_flags_require_manage_feature_flags(): void
    {
        $this->actingAsWithPermissions(['view plans']);

        $this->getJson('/admin/api/feature-flags')->assertForbidden();

        $this->actingAsWithPermissions(['view plans', 'manage feature flags']);
        $this->getJson('/admin/api/feature-flags')->assertOk();
    }

    public function test_settings_update_requires_edit_settings(): void
    {
        $this->actingAsWithPermissions(['view settings']);

        $this->putJson('/admin/api/settings', [
            'settings' => [['key' => 'currency', 'value' => 'EUR']],
        ])->assertForbidden();
    }
}
