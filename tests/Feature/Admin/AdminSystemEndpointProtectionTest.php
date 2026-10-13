<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Role;
use Database\Seeders\CentralRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Protection coverage for previously-untested admin/system endpoints.
 *
 * These endpoints had no authorization tests at all: activity logs, tenant
 * resource-usage metrics, settings reads, impersonation stop, and the
 * unauthorized page.
 */
class AdminSystemEndpointProtectionTest extends TestCase
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

    public function test_activity_log_endpoints_require_view_activity_logs_permission(): void
    {
        $endpoints = [
            '/admin/api/activity-logs',
            '/admin/api/activity-logs/log-names',
            '/admin/api/activity-logs/causers',
            '/admin/api/activity-logs/1',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)->assertUnauthorized();
        }

        $this->actingAsWithPermissions(['view tenants']);

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)->assertForbidden();
        }
    }

    public function test_activity_log_index_works_with_permission(): void
    {
        $this->actingAsWithPermissions(['view activity logs']);

        $this->getJson('/admin/api/activity-logs')
            ->assertOk()
            ->assertJsonStructure(['activities', 'meta' => ['current_page', 'last_page', 'total']]);

        $this->getJson('/admin/api/activity-logs/log-names')
            ->assertOk()
            ->assertJsonStructure(['log_names']);

        $this->getJson('/admin/api/activity-logs/causers')
            ->assertOk()
            ->assertJsonStructure(['causers', 'meta']);
    }

    public function test_tenant_metrics_endpoints_require_view_tenants_permission(): void
    {
        $endpoints = [
            '/admin/api/resource-usage',
            '/admin/api/resource-usage/summary',
            '/admin/api/resource-usage/tenant-xyz/history',
            '/admin/api/resource-usage/tenant-xyz',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)->assertUnauthorized();
        }

        $this->actingAsWithPermissions(['view staff']);

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)->assertForbidden();
        }
    }

    public function test_tenant_metrics_index_and_summary_work_with_permission(): void
    {
        $this->actingAsWithPermissions(['view tenants']);

        $this->getJson('/admin/api/resource-usage')
            ->assertOk()
            ->assertJsonStructure(['metrics', 'total', 'last_page', 'server']);

        $this->getJson('/admin/api/resource-usage/summary')
            ->assertOk()
            ->assertJsonStructure(['summary']);
    }

    public function test_tenant_metrics_show_reaches_controller_for_unknown_tenant(): void
    {
        $this->actingAsWithPermissions(['view tenants']);

        // Authorization passed: unknown tenant resolves to 404, not 403.
        $this->getJson('/admin/api/resource-usage/does-not-exist')->assertNotFound();
    }

    public function test_settings_read_requires_view_settings_permission(): void
    {
        $this->getJson('/admin/api/settings')->assertUnauthorized();
        $this->getJson('/admin/api/settings/currency')->assertUnauthorized();

        $this->actingAsWithPermissions(['edit settings']);

        $this->getJson('/admin/api/settings')->assertForbidden();
        $this->getJson('/admin/api/settings/currency')->assertForbidden();
    }

    public function test_settings_read_works_with_permission(): void
    {
        $this->actingAsWithPermissions(['view settings']);

        $this->getJson('/admin/api/settings')
            ->assertOk()
            ->assertJsonStructure(['settings']);

        $this->getJson('/admin/api/settings/unknown-key')->assertNotFound();
    }

    public function test_impersonation_stop_requires_impersonate_tenants_permission(): void
    {
        $this->postJson('/admin/api/impersonate/stop')->assertUnauthorized();

        $this->actingAsWithPermissions(['view tenants']);

        $this->postJson('/admin/api/impersonate/stop')->assertForbidden();
    }

    public function test_impersonation_stop_works_with_permission(): void
    {
        $this->actingAsWithPermissions(['impersonate tenants']);

        $this->postJson('/admin/api/impersonate/stop')
            ->assertOk()
            ->assertJsonPath('message', 'Impersonation stopped');
    }

    public function test_unauthorized_page_requires_authenticated_admin(): void
    {
        $this->get('/admin/unauthorized')->assertRedirect();

        $admin = AdminUser::factory()->create();
        $this->actingAs($admin, 'admin');

        $this->get('/admin/unauthorized')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Unauthorized'));
    }
}
