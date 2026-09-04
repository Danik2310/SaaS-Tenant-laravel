<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Models\TenantResourceUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Small dev experiment proving path-based tenancy identification:
 * a tenant is resolved from a URL path segment (inside the monolith) and
 * the request is routed to that tenant's own database — no external
 * subdomain/DNS/vhost involved.
 */
class PathIdentificationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $dbName;

    protected function setUp(): void
    {
        parent::setUp();

        // Creates the tenant DB + runs tenant migrations, but deliberately does
        // NOT initialize tenancy — the path middleware must do that from a
        // clean state during the request.
        $this->tenant = $this->createTestTenant();
        $this->tenant->update(['name' => 'Avventura Path Tenant']);
        $this->dbName = $this->tenant->database()->getName();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        if (isset($this->dbName)) {
            DB::statement("DROP DATABASE IF EXISTS `{$this->dbName}`");
        }

        if (isset($this->tenant)) {
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->delete();
            Tenant::where('id', $this->tenant->id)->delete();
        }

        parent::tearDown();
    }

    public function test_path_route_identifies_tenant_and_switches_database(): void
    {
        $response = $this->get("/p/{$this->tenant->id}/hello");

        $response->assertOk()
            ->assertJsonPath('tenant_id', $this->tenant->id)
            ->assertJsonPath('tenant_name', 'Avventura Path Tenant')
            ->assertJsonPath('connection', $this->dbName);
    }
}
