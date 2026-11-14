<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Auth;

use App\Models\Tenant;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Shared\Constants\OAuthProvider;
use App\Shared\Support\OAuthCallbackError;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Google sign-in for tenant users: the central callback resolves/creates the
 * tenant account, then hands a signed URL back to the tenant host which mints
 * the JWT cookie.
 *
 * The Socialite factory is replaced with a mock because the real driver would
 * call Google's token endpoint; everything after the identity is the code under
 * test.
 */
class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    private const CENTRAL = 'http://localhost';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        $this->initializeTenant($this->tenant);

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => self::CENTRAL.'/auth/google/callback',
        ]);
    }

    protected function tearDown(): void
    {
        $this->forgetTenant();

        if (isset($this->tenant)) {
            DB::statement("DROP DATABASE IF EXISTS `{$this->tenant->database()->getName()}`");
        }

        parent::tearDown();
    }

    // ──────────────────────────────────────────────
    //  Login / register screen
    // ──────────────────────────────────────────────

    public function test_login_screen_offers_google_sign_in_when_configured(): void
    {
        $this->get('http://'.$this->tenant->domain.'/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->has('providers', 1)
                ->where('providers.0.id', OAuthProvider::GOOGLE)
                ->where('providers.0.label', 'Google')
                // The link must point at the central entry point but carry the
                // tenant id through the flow.
                ->where('providers.0.url', fn (string $url): bool => str_contains($url, '/auth/google/redirect')
                    && str_contains($url, 'tenant='.$this->tenant->id)
                    && str_contains($url, 'intent=login')));
    }

    public function test_register_screen_offers_google_sign_up(): void
    {
        $this->get('http://'.$this->tenant->domain.'/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Register')
                ->has('providers', 1)
                ->where('providers.0.url', fn (string $url): bool => str_contains($url, 'intent=register')));
    }

    public function test_google_button_is_hidden_when_the_client_is_not_configured(): void
    {
        config(['services.google.client_id' => null]);

        $this->get('http://'.$this->tenant->domain.'/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->has('providers', 0)
                ->where('oauthError', null));
    }

    public function test_signed_oauth_error_is_rendered_on_the_login_screen(): void
    {
        $url = URL::temporarySignedRoute('login', now()->addMinute(), [
            'oauth_error' => OAuthCallbackError::CANCELLED,
        ]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('oauthError', fn ($message): bool => is_string($message) && str_contains($message, 'cancelled')));

        $this->get('http://'.$this->tenant->domain.'/login?oauth_error='.OAuthCallbackError::CANCELLED)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('oauthError', null));
    }

    // ──────────────────────────────────────────────
    //  Central leg
    // ──────────────────────────────────────────────

    public function test_redirect_remembers_the_tenant_and_forwards_to_google(): void
    {
        $this->fakeGoogleProvider($this->googleIdentity(), exchange: false);

        $response = $this->get(self::CENTRAL.'/auth/google/redirect?tenant='.$this->tenant->id.'&intent=register');

        $response->assertRedirect('https://accounts.google.com/o/oauth2/auth');
        $response->assertSessionHas('oauth.tenant', $this->tenant->id);
        $response->assertSessionHas('oauth.intent', 'register');
    }

    public function test_redirect_rejects_an_unknown_tenant(): void
    {
        $this->get(self::CENTRAL.'/auth/google/redirect?tenant=does-not-exist')->assertNotFound();
    }

    public function test_callback_creates_a_verified_passwordless_user_and_hands_off(): void
    {
        Event::fake([Registered::class]);

        $this->fakeGoogleProvider($this->googleIdentity());

        $response = $this->runCallback();

        $response->assertRedirectContains($this->tenant->domain.'/auth/google/complete');

        $this->inspectTenantDatabase();

        $user = DB::connection('tenant')->table('users')->where('email', 'ada@example.com')->first();

        $this->assertNotNull($user, 'the Google user should have been created in the tenant database');
        $this->assertNull($user->password, 'an OAuth account is passwordless');
        $this->assertNotNull($user->email_verified_at, 'Google verified the address');
        $this->assertTrue((bool) $user->is_active);

        $this->assertDatabaseHas('oauth_accounts', [
            'user_id' => $user->id,
            'provider' => OAuthProvider::GOOGLE,
            'provider_user_id' => 'google-123',
            'provider_email' => 'ada@example.com',
        ], 'tenant');

        // The only `Registered` listener mails a verification link; Google has
        // already verified the address, so the event must not fire.
        Event::assertNotDispatched(Registered::class);

        // A null hash must never be accepted as a valid credential.
        $this->assertFalse(Auth::guard('web')->attempt(['email' => 'ada@example.com', 'password' => '']));
        $this->assertFalse(Auth::guard('web')->attempt(['email' => 'ada@example.com', 'password' => 'password']));
    }

    public function test_callback_links_an_existing_account_by_verified_email(): void
    {
        $existing = User::factory()->create(['email' => 'ada@example.com']);

        $this->fakeGoogleProvider($this->googleIdentity());

        $this->runCallback()->assertRedirectContains($this->tenant->domain.'/auth/google/complete');

        $this->inspectTenantDatabase();

        $this->assertSame(1, DB::connection('tenant')->table('users')->count(), 'no duplicate user');

        $this->assertDatabaseHas('oauth_accounts', [
            'user_id' => $existing->id,
            'provider' => OAuthProvider::GOOGLE,
            'provider_user_id' => 'google-123',
        ], 'tenant');
    }

    public function test_callback_refuses_an_unverified_email_for_an_existing_account(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->fakeGoogleProvider($this->googleIdentity(emailVerified: false));

        $this->runCallback()->assertRedirectContains('oauth_error='.OAuthCallbackError::UNVERIFIED);

        $this->inspectTenantDatabase();

        $this->assertDatabaseCount('oauth_accounts', 0, 'tenant');
    }

    public function test_callback_refuses_to_create_an_unverified_account(): void
    {
        $this->fakeGoogleProvider($this->googleIdentity(emailVerified: false));

        $this->runCallback()->assertRedirectContains('oauth_error='.OAuthCallbackError::UNVERIFIED);

        $this->inspectTenantDatabase();

        $this->assertDatabaseCount('users', 0, 'tenant');
    }

    public function test_callback_refuses_a_deactivated_account(): void
    {
        User::factory()->inactive()->create(['email' => 'ada@example.com']);

        $this->fakeGoogleProvider($this->googleIdentity());

        $this->runCallback()->assertRedirectContains('oauth_error='.OAuthCallbackError::INACTIVE);

        $this->inspectTenantDatabase();

        $this->assertDatabaseCount('oauth_accounts', 0, 'tenant');
    }

    public function test_callback_does_not_resurrect_a_soft_deleted_account(): void
    {
        $deleted = User::factory()->create(['email' => 'ada@example.com']);
        $deleted->delete();

        $this->fakeGoogleProvider($this->googleIdentity());

        $this->runCallback()->assertRedirectContains($this->tenant->domain.'/auth/google/complete');

        $this->inspectTenantDatabase();

        $this->assertSame(2, DB::connection('tenant')->table('users')->count(), 'soft-deleted row is kept, a new account is created');

        $created = DB::connection('tenant')->table('users')->whereNull('deleted_at')->first();

        $this->assertNotNull($created);
        $this->assertNotSame($deleted->id, $created->id);
    }

    public function test_callback_refuses_to_exceed_the_plan_user_limit(): void
    {
        // The default (free) plan allows two users.
        User::factory()->count(2)->create();

        $this->fakeGoogleProvider($this->googleIdentity(email: 'third@example.com'));

        $this->runCallback()->assertRedirectContains('oauth_error='.OAuthCallbackError::USER_LIMIT);

        $this->inspectTenantDatabase();

        $this->assertSame(2, DB::connection('tenant')->table('users')->count());
    }

    public function test_cancelled_consent_does_not_exchange_the_code(): void
    {
        // `user()` must never be called when Google returns an error instead of a code.
        $this->fakeGoogleProvider($this->googleIdentity(), exchange: false);

        $this->runCallback(['error' => 'access_denied'])
            ->assertRedirectContains('oauth_error='.OAuthCallbackError::CANCELLED);
    }

    public function test_callback_without_a_tenant_in_session_returns_to_the_central_home(): void
    {
        $this->fakeGoogleProvider($this->googleIdentity(), exchange: false);

        $this->get(self::CENTRAL.'/auth/google/callback?code=fake-code')
            ->assertRedirect('/');
    }

    // ──────────────────────────────────────────────
    //  Tenant leg
    // ──────────────────────────────────────────────

    public function test_signed_handoff_signs_the_user_in(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $url = URL::temporarySignedRoute('auth.google.complete', now()->addMinute(), ['user' => $user->id]);

        $this->get($url)
            ->assertRedirect(RouteServiceProvider::HOME)
            ->assertCookie((string) config('jwt.cookie_key_name', 'token'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_handoff_is_rejected_without_a_valid_signature(): void
    {
        $user = User::factory()->create();

        $this->get('http://'.$this->tenant->domain.'/auth/google/complete?user='.$user->id)
            ->assertForbidden();
    }

    public function test_handoff_refuses_a_deactivated_user(): void
    {
        $user = User::factory()->inactive()->create();

        $url = URL::temporarySignedRoute('auth.google.complete', now()->addMinute(), ['user' => $user->id]);

        $this->get($url)
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    private function googleIdentity(
        string $email = 'ada@example.com',
        string $providerUserId = 'google-123',
        string $name = 'Ada Lovelace',
        bool $emailVerified = true,
    ): SocialiteUser {
        $identity = (new SocialiteUser)->map([
            'id' => $providerUserId,
            'nickname' => null,
            'name' => $name,
            'email' => $email,
            'avatar' => null,
        ]);

        $identity->setRaw([
            'sub' => $providerUserId,
            'email' => $email,
            'email_verified' => $emailVerified,
        ]);

        return $identity;
    }

    private function fakeGoogleProvider(SocialiteUser $identity, bool $exchange = true): void
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('with')->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(redirect()->away('https://accounts.google.com/o/oauth2/auth'));

        if ($exchange) {
            $provider->shouldReceive('user')->andReturn($identity);
        } else {
            $provider->shouldNotReceive('user');
        }

        $factory = Mockery::mock(SocialiteFactory::class);
        $factory->shouldReceive('driver')->with(OAuthProvider::GOOGLE)->andReturn($provider);

        $this->app->instance(SocialiteFactory::class, $factory);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function runCallback(array $query = []): TestResponse
    {
        $query += ['code' => 'fake-code', 'state' => 'fake-state'];

        return $this->withSession(['oauth.tenant' => $this->tenant->id, 'oauth.intent' => 'login'])
            ->get(self::CENTRAL.'/auth/google/callback?'.http_build_query($query));
    }

    /**
     * The callback always ends tenancy in its `finally`, which unregisters the
     * `tenant` connection — re-enter it to inspect the tenant database.
     */
    private function inspectTenantDatabase(): void
    {
        $this->initializeTenant($this->tenant);
    }
}
