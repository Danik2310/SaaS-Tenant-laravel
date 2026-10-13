<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Role;
use Database\Seeders\CentralRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Missing 403 coverage for sensitive admin write actions.
 *
 * Each endpoint already had a happy-path test and/or a guest check; these
 * assert that an authenticated admin who holds a *different* permission is
 * forbidden.
 */
class AdminWriteProtectionTest extends TestCase
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

    private function assertNotForbidden(TestResponse $response): void
    {
        $this->assertNotSame(403, $response->getStatusCode(), 'Expected request to pass authorization middleware.');
    }

    public function test_plan_update_requires_edit_plans(): void
    {
        $this->actingAsWithPermissions(['view plans']);
        $this->putJson('/admin/api/plans/1', ['name' => 'Blocked'])->assertForbidden();

        $this->actingAsWithPermissions(['view plans', 'edit plans']);
        $this->assertNotForbidden($this->putJson('/admin/api/plans/1', ['name' => 'Allowed']));
    }

    public function test_payment_method_read_requires_view_permission(): void
    {
        $this->actingAsWithPermissions(['create payment methods']);

        $this->getJson('/admin/api/payment-methods')->assertForbidden();
        $this->getJson('/admin/api/payment-methods/1')->assertForbidden();
    }

    public function test_payment_method_writes_require_respective_permissions(): void
    {
        $this->actingAsWithPermissions(['view payment methods']);

        $this->postJson('/admin/api/payment-methods', [])->assertForbidden();
        $this->putJson('/admin/api/payment-methods/1', [])->assertForbidden();
        $this->patchJson('/admin/api/payment-methods/1/toggle-active')->assertForbidden();
        $this->deleteJson('/admin/api/payment-methods/1')->assertForbidden();
    }

    public function test_role_writes_require_matching_permissions(): void
    {
        $this->actingAsWithPermissions(['view roles']);

        $this->postJson('/admin/api/roles', [])->assertForbidden();
        $this->putJson('/admin/api/roles/1', [])->assertForbidden();
        $this->deleteJson('/admin/api/roles/1')->assertForbidden();
    }

    public function test_permission_writes_require_matching_permissions(): void
    {
        $this->actingAsWithPermissions(['view permissions']);

        $this->postJson('/admin/api/permissions', [])->assertForbidden();
        $this->putJson('/admin/api/permissions/1', [])->assertForbidden();
        $this->deleteJson('/admin/api/permissions/1')->assertForbidden();
    }

    public function test_tenant_action_endpoints_require_specific_permissions(): void
    {
        $this->actingAsWithPermissions(['view tenants']);

        $this->putJson('/admin/api/tenants/1/plan', ['plan_id' => 1])->assertForbidden();
        $this->postJson('/admin/api/tenants/1/migrate')->assertForbidden();
        $this->patchJson('/admin/api/tenants/1/restore')->assertForbidden();

        $this->actingAsWithPermissions(['edit tenants']);
        $this->getJson('/admin/api/tenants/1/database')->assertForbidden();
    }

    public function test_bulk_operation_requires_action_specific_permission(): void
    {
        $this->actingAsWithPermissions(['edit tenants']);

        // Route middleware allows edit|delete|restore; the FormRequest must
        // then reject a delete action for an edit-only admin.
        $this->postJson('/admin/api/tenants/bulk', [
            'action' => 'delete',
            'tenant_ids' => ['tenant-1'],
        ])->assertForbidden();

        // A suspend action is covered by edit tenants, so it reaches validation.
        $this->assertNotForbidden($this->postJson('/admin/api/tenants/bulk', [
            'action' => 'suspend',
            'tenant_ids' => ['tenant-1'],
        ]));
    }

    public function test_staff_edit_cluster_requires_edit_staff(): void
    {
        $this->actingAsWithPermissions(['view staff']);

        $this->putJson('/admin/api/staff/1', ['name' => 'Blocked'])->assertForbidden();
        $this->patchJson('/admin/api/staff/1/restore')->assertForbidden();
        $this->patchJson('/admin/api/staff/1/toggle-status')->assertForbidden();
        $this->postJson('/admin/api/staff/1/roles', ['roles' => []])->assertForbidden();
        $this->postJson('/admin/api/staff/1/permissions', ['permissions' => []])->assertForbidden();
    }
}
