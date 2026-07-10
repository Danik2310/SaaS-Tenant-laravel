<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Shared\Middleware\CheckTenantState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CheckTenantStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->beforeApplicationDestroyed(function () {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        });
    }

    /**
     * Note: These tests create tenants directly via Tenant::create() rather than
     * using createTestTenant() + initializeTenant() because:
     * 1. The middleware only reads tenant()->status from the central DB
     * 2. No tenant database is needed — we never write to tenant tables
     * 3. initializeTenant() would set config('database.default' => 'tenant')
     *    pointing to a non-existent database, causing failures
     */
    public function test_active_tenant_passes_middleware()
    {
        $tenant = Tenant::create([
            'id' => 'test-tenant-'.uniqid(),
            'name' => 'Test Tenant',
            'status' => 'Active',
        ]);

        tenancy()->initialize($tenant);

        $middleware = new CheckTenantState;
        $request = Request::create('/');
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());

        tenancy()->end();
    }

    public function test_suspended_tenant_returns_403()
    {
        $tenant = Tenant::create([
            'id' => 'suspended-tenant-'.uniqid(),
            'name' => 'Suspended Tenant',
            'status' => 'Suspended',
        ]);

        tenancy()->initialize($tenant);

        $middleware = new CheckTenantState;
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_Accept' => 'application/json']);
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(403, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Your account has been suspended. Please contact support.', $data['message']);

        tenancy()->end();
    }

    public function test_deleted_tenant_returns_403_with_deleted_message()
    {
        $tenant = Tenant::create([
            'id' => 'deleted-tenant-'.uniqid(),
            'name' => 'Deleted Tenant',
            'status' => 'Deleted',
        ]);

        tenancy()->initialize($tenant);

        $middleware = new CheckTenantState;
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_Accept' => 'application/json']);
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(403, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('This account has been deleted. Please contact support.', $data['message']);

        tenancy()->end();
    }

    public function test_middleware_passes_when_no_tenant_context()
    {
        $middleware = new CheckTenantState;
        $request = Request::create('/');
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }
}
