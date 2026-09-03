<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Domain;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Shared\Support\ImpersonationToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTestTenant();
    }

    protected function tearDown(): void
    {
        if (isset($this->tenant)) {
            DB::statement("DROP DATABASE IF EXISTS `{$this->tenant->database()->getName()}`");
        }
        parent::tearDown();
    }

    public function test_admin_can_start_impersonation_and_get_signed_entry_url(): void
    {
        $this->setUpAdminAuth();

        $response = $this->postJson('/admin/api/impersonate', [
            'tenant_id' => $this->tenant->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Impersonation started')
            ->assertJsonPath('domain', $this->tenant->domain)
            ->assertJsonStructure(['enterUrl']);

        $query = parse_url($response->json('enterUrl'), PHP_URL_QUERY);
        parse_str($query, $params);

        $this->assertArrayHasKey('impersonate_token', $params);
        $this->assertNotNull(ImpersonationToken::verify($params['impersonate_token']));

        // The signed token must be bound to the target tenant.
        $this->assertSame($this->tenant->id, ImpersonationToken::verify($params['impersonate_token'])['tenant_id']);
    }

    public function test_admin_without_impersonation_permission_is_rejected(): void
    {
        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        $permission = Permission::firstOrCreate(['name' => 'impersonate tenants', 'guard_name' => 'admin']);

        $admin = AdminUser::factory()->create();
        $admin->assignRole($role);

        // Ensure the role still holds the permission so the failure is purely
        // due to missing auth rather than a missing permission row.
        $role->givePermissionTo($permission);

        $this->actingAs($admin, 'admin');

        // Remove the permission from the authenticated user via a slim role.
        $slim = Role::firstOrCreate(['name' => 'no-impersonate', 'guard_name' => 'admin']);
        $admin->syncRoles($slim);

        $response = $this->postJson('/admin/api/impersonate', [
            'tenant_id' => $this->tenant->id,
        ]);

        $response->assertForbidden();
    }

    public function test_starting_impersonation_when_already_active_is_rejected(): void
    {
        $this->setUpAdminAuth();

        session(['impersonate_tenant' => $this->tenant->id, 'impersonate_started_at' => now()->timestamp]);

        $response = $this->postJson('/admin/api/impersonate', [
            'tenant_id' => $this->tenant->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Already impersonating a tenant. Stop current impersonation first.');
    }

    public function test_starting_impersonation_for_tenant_without_domain_is_rejected(): void
    {
        $this->setUpAdminAuth();

        // The real test tenant has a domain; strip it to simulate a domainless tenant.
        Domain::where('tenant_id', $this->tenant->id)->delete();

        $response = $this->postJson('/admin/api/impersonate', [
            'tenant_id' => $this->tenant->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Tenant has no domain configured');
    }
}
