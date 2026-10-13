<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The central marketing homepage is constrained to central domains; tenant
 * hosts serve their own root route instead.
 */
class TenantRootRouteTest extends TestCase
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

    public function test_tenant_root_redirects_guest_to_tenant_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_central_welcome_is_not_served_on_tenant_host(): void
    {
        $this->get('/')->assertRedirect();
    }

    public function test_central_welcome_renders_on_central_domain(): void
    {
        // Leave tenant context and hit the central domain (localhost).
        $this->forgetTenant();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
    }
}
