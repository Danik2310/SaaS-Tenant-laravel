<?php

namespace Tests\Feature\Tenant\Auth;

use App\Models\Tenant;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Shared\Support\AuthSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * Server-side enforcement of the two login clocks:
 *  - idle: `session.idle_lifetime` minutes between authenticated requests;
 *  - absolute: `session.absolute_lifetime` minutes since login, never extended.
 *
 * Both are anchored in the Laravel session by {@see AuthSession}; a bearer
 * token without anchors is treated as expired (fail-closed).
 */
class SessionLifetimeTest extends TestCase
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

    private function login(): User
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(RouteServiceProvider::HOME);

        return $user;
    }

    public function test_login_starts_the_session_clocks(): void
    {
        $this->login();

        $this->assertNotNull(session(AuthSession::STARTED_AT));
        $this->assertSame(
            session(AuthSession::STARTED_AT),
            session(AuthSession::LAST_ACTIVITY)
        );
    }

    public function test_activity_inside_the_idle_window_keeps_the_session_alive(): void
    {
        $this->actingAs(User::factory()->create());

        $this->travel(29)->minutes();
        $this->get('/dashboard')->assertOk();

        // The previous request slid `last_activity`, so another 29 minutes
        // still leaves the session inside the 30-minute window.
        $this->travel(29)->minutes();
        $this->get('/dashboard')->assertOk();
    }

    public function test_idle_session_is_revoked(): void
    {
        $this->actingAs(User::factory()->create());

        $this->travel(31)->minutes();

        $this->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertCookieExpired('token');

        $this->assertGuest('web');
    }

    public function test_session_does_not_slide_past_the_absolute_lifetime(): void
    {
        $this->actingAs(User::factory()->create());

        // Stay active: every request resets the idle clock, but the absolute
        // anchor written at login must still end the session. Steps stay under
        // the idle window so only the absolute clock can fire.
        for ($step = 0; $step < 36; $step++) {
            $this->travel(20)->minutes();
            $this->get('/dashboard')->assertOk();
        }

        $this->travel(20)->minutes();

        $this->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertCookieExpired('token');
    }

    public function test_missing_login_anchors_revoke_the_session(): void
    {
        $this->actingAs(User::factory()->create());

        // Simulate a Laravel session cookie that expired while the JWT cookie
        // survived: the credential is present, the login proof is not.
        $this->flushSession();

        $this->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertCookieExpired('token');
    }

    public function test_logout_blacklists_the_tenant_token(): void
    {
        $user = User::factory()->create();

        $login = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(RouteServiceProvider::HOME);

        $token = collect($login->headers->getCookies())
            ->first(fn (Cookie $cookie) => $cookie->getName() === 'token')
            ?->getValue();

        $this->assertNotNull($token, 'Expected a token cookie on the login response.');

        $this->withUnencryptedCookie('token', $token)
            ->post('/logout')
            ->assertRedirect('/');

        // A fresh session with the old token must still be rejected: the token
        // itself was blacklisted server-side, not just removed from the browser.
        $this->withUnencryptedCookie('token', $token)
            ->withAuthSession()
            ->get('/dashboard')
            ->assertRedirect(route('login'));
    }

    public function test_impersonation_ignores_the_login_clocks(): void
    {
        $user = User::factory()->create();
        $token = Auth::guard('web')->login($user);

        // God mode has its own TTL and no login anchors; a leftover tenant JWT
        // in the same browser must not revoke the impersonation session.
        $this->withUnencryptedCookie('token', $token)
            ->withSession([
                'impersonation' => [
                    'tenant_id' => $this->tenant->id,
                    'admin_id' => '1',
                    'admin_name' => 'Super Admin',
                    'admin_email' => 'admin@example.com',
                    'started_at' => now()->timestamp,
                    'ttl' => 60,
                ],
            ])
            ->get('/dashboard')
            ->assertOk();
    }
}
