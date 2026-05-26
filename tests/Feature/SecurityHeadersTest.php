<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()['env'] = 'testing';
    }

    public function test_middleware_sets_all_security_headers(): void
    {
        $middleware = new SecurityHeaders;
        $request = Request::create('/');
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_dev_csp_includes_vite_dev_server_url(): void
    {
        app()['env'] = 'local';

        $middleware = new SecurityHeaders;
        $request = Request::create('/');
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('http://127.0.0.1:5174', $csp);
        $this->assertStringContainsString('ws://127.0.0.1:5174', $csp);
        $this->assertStringContainsString("'unsafe-eval'", $csp);
    }

    public function test_prod_csp_excludes_vite_dev_server_url_and_unsafe_eval(): void
    {
        app()['env'] = 'production';

        $middleware = new SecurityHeaders;
        $request = Request::create('/');
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('127.0.0.1:5174', $csp);
        $this->assertStringNotContainsString('ws://', $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
    }

    public function test_dev_csp_contains_expected_directives(): void
    {
        app()['env'] = 'local';

        $middleware = new SecurityHeaders;
        $request = Request::create('/');
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' 'unsafe-eval'", $csp);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline' https://fonts.bunny.net", $csp);
        $this->assertStringContainsString("font-src 'self' data: https://fonts.bunny.net", $csp);
        $this->assertStringContainsString("img-src 'self' data:", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_prod_csp_contains_expected_directives(): void
    {
        app()['env'] = 'production';

        $middleware = new SecurityHeaders;
        $request = Request::create('/');
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline' https://fonts.bunny.net", $csp);
        $this->assertStringContainsString("font-src 'self' data: https://fonts.bunny.net", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_csp_has_no_wildcards(): void
    {
        $middleware = new SecurityHeaders;
        $request = Request::create('/');
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('*', $csp);
    }

    public function test_security_headers_present_on_admin_login_page(): void
    {
        $response = $this->get('/central/login');

        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_security_headers_present_on_admin_dashboard(): void
    {
        $admin = AdminUser::factory()->create();

        $this->actingAs($admin, 'admin');

        $response = $this->get('/admin/dashboard');

        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }
}
