<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the super-admin role if it doesn't exist
        $role = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'admin'
        ]);

        // Create permissions if they don't exist
        $permissions = [
            'manage tenants',
            'manage staff',
            'manage plans',
            'impersonate tenants',
            'manage profile'
        ];

        foreach ($permissions as $permissionName) {
            $permission = \Spatie\Permission\Models\Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'admin'
            ]);
            $role->givePermissionTo($permission);
        }

        // Create admin user with permissions
        $admin = AdminUser::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin, 'admin');
    }

    /**
     * 🛡️ Test: Database credentials should NOT be exposed in API responses
     */
    public function test_tenant_database_endpoint_does_not_expose_credentials()
    {
        $tenant = $this->createTestTenant();

        $response = $this->getJson("/admin/api/tenants/{$tenant->id}/database");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'database' => [
                        'name',
                        'connection',
                        'host',
                        'port'
                    ]
                ])
                ->assertJsonMissing(['database.username'])
                ->assertJsonMissing(['database.password']);
    }

    /**
     * 🛡️ Test: Admin users should not have password field in API responses
     */
    public function test_admin_users_api_does_not_expose_passwords()
    {
        AdminUser::factory()->create(['email' => 'test@example.com']);

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
                            'permissions'
                        ]
                    ]
                ])
                ->assertJsonMissing(['staff.*.password']);
    }

    /**
     * 🛡️ Test: Tenants API should not expose sensitive internal fields
     */
    public function test_tenants_api_does_not_expose_sensitive_fields()
    {
        $tenant = $this->createTestTenant();

        $response = $this->getJson('/admin/api/tenants');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'tenants' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                            'domain',
                            'status',
                            'created_at',
                            'updated_at'
                        ]
                    ]
                ])
                ->assertJsonMissing(['tenants.*.tenancy_db_name'])
                ->assertJsonMissing(['tenants.*.data_placeholder']);
    }

    /**
     * 🛡️ Test: Users without specific permissions cannot access protected routes
     */
    public function test_unauthorized_access_to_admin_routes_blocked()
    {
        // Create admin user without permissions
        $admin = \App\Models\AdminUser::factory()->create();
        $this->actingAs($admin, 'admin');

        // Should be blocked without 'manage tenants' permission
        $this->get('/admin/api/tenants')->assertStatus(403);

        // Should be blocked without 'manage staff' permission
        $this->get('/admin/api/staff')->assertStatus(403);
    }

    /**
     * 🛡️ Test: Users without permissions cannot access protected routes
     */
    public function test_insufficient_permissions_blocked()
    {
        // Create admin without permissions
        $admin = AdminUser::factory()->create();
        $this->actingAs($admin, 'admin');

        // Should be blocked without 'manage tenants' permission
        $this->get('/admin/api/tenants')->assertStatus(403);
        $this->post('/admin/api/tenants')->assertStatus(403);

        // Should be blocked without 'manage staff' permission
        $this->get('/admin/api/staff')->assertStatus(403);
        $this->post('/admin/api/staff')->assertStatus(403);
    }
}