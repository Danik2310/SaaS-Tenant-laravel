<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Impersonation;

use App\Models\Tenant;
use App\Shared\Middleware\EnsureImpersonationValid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureImpersonationValidTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTestTenant();
        $this->initializeTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        $this->forgetTenant();
        if (isset($this->tenant)) {
            DB::statement("DROP DATABASE IF EXISTS `{$this->tenant->database()->getName()}`");
        }
        parent::tearDown();
    }

    private function middleware(): EnsureImpersonationValid
    {
        return new EnsureImpersonationValid;
    }

    private function act(Request $request, ?callable $next = null)
    {
        $next ??= fn () => response('ok');

        return $this->middleware()->handle($request, $next);
    }

    private function impersonation(array $overrides = []): array
    {
        return array_merge([
            'admin_id' => '1',
            'admin_name' => 'Super Admin',
            'tenant_id' => $this->tenant->id,
            'started_at' => time(),
            'ttl' => 60,
        ], $overrides);
    }

    public function test_blocks_mutating_request_in_read_only_mode(): void
    {
        session(['impersonation' => $this->impersonation()]);

        $request = Request::create('/admin/products', 'POST');

        try {
            $this->act($request);
            $this->fail('Expected an HttpException 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_permits_get_request(): void
    {
        session(['impersonation' => $this->impersonation()]);

        $response = $this->act(Request::create('/dashboard', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_permits_stop_route_even_though_post(): void
    {
        session(['impersonation' => $this->impersonation()]);

        $request = Request::create('/god-mode/stop', 'POST');
        $request->setRouteResolver(fn () => Route::getRoutes()->getByName('god-mode.stop'));

        $response = $this->act($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_expired_session_is_cleared(): void
    {
        session(['impersonation' => $this->impersonation(['started_at' => time() - (61 * 60)])]);

        $response = $this->act(Request::create('/dashboard', 'GET'));

        $this->assertFalse(session()->has('impersonation'));
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_mismatched_tenant_is_rejected(): void
    {
        session(['impersonation' => $this->impersonation(['tenant_id' => 'different-tenant'])]);

        try {
            $this->act(Request::create('/dashboard', 'GET'));
            $this->fail('Expected an HttpException 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
