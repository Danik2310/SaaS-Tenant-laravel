<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Impersonation;

use App\Models\Tenant;
use App\Shared\Support\ImpersonationToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression guard for the tenant middleware order.
 *
 * God mode seeds the `impersonation` session then redirects to the dashboard.
 * The request that lands on the dashboard must run `impersonation.session`
 * (which injects the synthetic admin identity) BEFORE `auth`, otherwise the
 * guard sees no user and bounces the admin back to login. The older EntryTest
 * never followed the redirect, so a broken order still passed green.
 */
class GodModeDashboardAccessTest extends TestCase
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

    private function entryUrl(): string
    {
        $token = ImpersonationToken::sign([
            'tenant_id' => $this->tenant->id,
            'admin_id' => '1',
            'admin_name' => 'Super Admin',
            'admin_email' => 'admin@example.com',
        ], (int) config('impersonation.token_ttl', 300));

        return '/god-mode/enter?impersonate_token='.$token;
    }

    public function test_god_mode_session_reaches_the_dashboard_after_redirect(): void
    {
        $this->get($this->entryUrl())
            ->assertRedirect(route('tenant.dashboard'));

        // Following the redirect exercises the full auth pipeline with the
        // impersonation session active. A 302 here means `auth` ran first.
        $this->get(route('tenant.dashboard'))->assertOk();
    }
}
