<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTenantState;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CheckTenantStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_tenant_passes_middleware()
    {
        $tenant = Tenant::create([
            'id' => 'test-tenant-' . uniqid(),
            'name' => 'Test Tenant',
            'status' => 'Active',
        ]);

        tenancy()->initialize($tenant);

        $middleware = new CheckTenantState();
        $request = Request::create('/');
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());

        tenancy()->end();
    }

    public function test_suspended_tenant_returns_403()
    {
        $tenant = Tenant::create([
            'id' => 'suspended-tenant-' . uniqid(),
            'name' => 'Suspended Tenant',
            'status' => 'Suspended',
        ]);

        tenancy()->initialize($tenant);

        $middleware = new CheckTenantState();
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
            'id' => 'deleted-tenant-' . uniqid(),
            'name' => 'Deleted Tenant',
            'status' => 'Deleted',
        ]);

        tenancy()->initialize($tenant);

        $middleware = new CheckTenantState();
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_Accept' => 'application/json']);
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(403, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('This account has been deleted. Please contact support.', $data['message']);

        tenancy()->end();
    }

    public function test_middleware_passes_when_no_tenant_context()
    {
        $middleware = new CheckTenantState();
        $request = Request::create('/');
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }
}
