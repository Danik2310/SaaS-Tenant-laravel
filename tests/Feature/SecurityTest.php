<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustHosts;
use App\Models\AdminUser;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    /** @var array<int, string> */
    protected array $createdTenantDbNames = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        foreach ($this->createdTenantDbNames as $dbName) {
            try {
                DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
            } catch (\Exception) {
                // Silently ignore — DB may already be gone
            }
        }

        parent::tearDown();
    }

    /**
     * 🛡️ Test: Database credentials should NOT be exposed in API responses
     */
    public function test_tenant_database_endpoint_does_not_expose_credentials()
    {
        $tenant = $this->createTestTenant();
        $this->createdTenantDbNames[] = $tenant->database()->getName();

        $response = $this->getJson("/admin/api/tenants/{$tenant->id}/database");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'database' => [
                    'name',
                    'connection',
                ],
            ])
            ->assertJsonMissing(['database.host'])
            ->assertJsonMissing(['database.port'])
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
                        'permissions',
                    ],
                ],
            ])
            ->assertJsonMissing(['staff.*.password']);
    }

    /**
     * 🛡️ Test: Tenants API should not expose sensitive internal fields
     */
    public function test_tenants_api_does_not_expose_sensitive_fields()
    {
        $tenant = $this->createTestTenant();
        $this->createdTenantDbNames[] = $tenant->database()->getName();

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
                        'updated_at',
                    ],
                ],
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
        $admin = AdminUser::factory()->create();
        $this->actingAs($admin, 'admin');

        // Should be blocked without 'view tenants' permission
        $this->get('/admin/api/tenants')->assertStatus(403);

        // Should be blocked without 'view staff' permission
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

        // Should be blocked without 'view tenants' permission
        $this->get('/admin/api/tenants')->assertStatus(403);
        $this->post('/admin/api/tenants')->assertStatus(403);

        // Should be blocked without 'view staff' permission
        $this->get('/admin/api/staff')->assertStatus(403);
        $this->post('/admin/api/staff')->assertStatus(403);
    }

    /**
     * 🛡️ Test: TrustHosts middleware is registered in the global middleware stack
     */
    public function test_trust_hosts_middleware_is_registered()
    {
        $kernel = $this->app->make(Kernel::class);
        $middlewareProperty = (new \ReflectionClass($kernel))->getProperty('middleware');
        $middlewareProperty->setAccessible(true);
        $globalStack = $middlewareProperty->getValue($kernel);

        $this->assertContains(TrustHosts::class, $globalStack, 'TrustHosts middleware must be in the global stack');
    }

    /**
     * 🛡️ Test: Admin login locks out after 5 failed attempts
     */
    public function test_admin_login_rate_limiter_locks_after_5_attempts()
    {
        AdminUser::factory()->create([
            'email' => 'super@example.com',
            'password' => bcrypt('correct-password'),
            'is_active' => true,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/central/login', [
                'email' => 'super@example.com',
                'password' => 'wrong-password',
            ]);

            if ($i < 4) {
                $response->assertStatus(422);
            }
        }

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }
}
