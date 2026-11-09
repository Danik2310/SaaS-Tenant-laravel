<?php

namespace Tests\Feature\Tenant\Auth;

use App\Models\Tenant;
use App\Models\User;
use App\Tenants\Notifications\TenantEmailVerification;
use App\Tenants\Support\TenantVerificationCooldown;
use App\Tenants\Support\TenantVerificationUrl;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
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

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/verify-email')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/VerifyEmail')
                ->where('email', $user->email)
            );
    }

    public function test_email_can_be_verified_without_a_session(): void
    {
        // The link is the credential: mail clients open it in whatever browser
        // is handy, so a fresh visitor must be able to confirm without first
        // being pushed through the login form.
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = $this->verificationUrl($user);

        $response = $this->get($verificationUrl);

        $response->assertRedirect();

        $pendingUrl = (string) $response->headers->get('Location');

        $this->assertStringContainsString('/register/pending', $pendingUrl);
        $this->assertStringContainsString('signature=', $pendingUrl);

        $this->get($pendingUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/TenantRegisterSuccess')
                ->where('email', $user->email)
                ->where('verified', true)
                ->where('domain', $this->tenant->domain)
                ->has('loginUrl')
                ->has('resendUrl')
            );

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->get($verificationUrl)
            ->assertStatus(403)
            ->assertInertia(fn (Assert $page) => $page->component('Auth/EmailVerificationExpired'));

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_expired_verification_link_renders_the_expired_page(): void
    {
        $user = User::factory()->unverified()->create();

        $expiredUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->get($expiredUrl)
            ->assertStatus(403)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/EmailVerificationExpired')
                ->has('loginUrl')
            );

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_link_pointing_at_a_missing_user_renders_the_expired_page(): void
    {
        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => 999999, 'hash' => sha1('nobody@acme.test')]
        );

        $this->get($signedUrl)
            ->assertStatus(403)
            ->assertInertia(fn (Assert $page) => $page->component('Auth/EmailVerificationExpired'));
    }

    public function test_verification_is_idempotent_and_does_not_replay_the_event(): void
    {
        $user = User::factory()->unverified()->create();
        $verificationUrl = $this->verificationUrl($user);

        $this->get($verificationUrl)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        Event::fake();

        $response = $this->get($verificationUrl);

        $response->assertRedirect();

        $this->get((string) $response->headers->get('Location'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/TenantRegisterSuccess')
                ->where('verified', true)
            );

        Event::assertNotDispatched(Verified::class);
    }

    public function test_registered_event_sends_the_tenant_rooted_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        event(new Registered($user));

        $url = null;

        Notification::assertSentTo(
            $user,
            TenantEmailVerification::class,
            function (TenantEmailVerification $notification) use (&$url): bool {
                $url = $notification->verificationUrl;

                return true;
            }
        );

        $this->assertIsString($url);
        $this->assertStringStartsWith(
            'http://'.$this->tenant->domain.'/verify-email/'.$user->getKey().'/',
            $url
        );
    }

    public function test_email_can_be_verified_from_the_tenant_rooted_link(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        event(new Registered($user));

        $url = null;

        Notification::assertSentTo(
            $user,
            TenantEmailVerification::class,
            function (TenantEmailVerification $notification) use (&$url): bool {
                $url = $notification->verificationUrl;

                return true;
            }
        );

        $this->get($url)->assertRedirect();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_pending_page_shows_the_waiting_state_for_an_unverified_owner(): void
    {
        $user = User::factory()->unverified()->create();

        $url = TenantVerificationUrl::pendingFor($this->tenant, $user->email);

        $this->assertNotNull($url);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/TenantRegisterSuccess')
                ->where('email', $user->email)
                ->where('verified', false)
                ->where('domain', $this->tenant->domain)
                ->where('tenantReady', true)
                ->where('cooldownSeconds', 0)
                ->has('resendUrl')
                ->has('loginUrl')
            );
    }

    public function test_pending_page_rejects_a_tampered_signature(): void
    {
        $user = User::factory()->unverified()->create();

        $url = (string) TenantVerificationUrl::pendingFor($this->tenant, $user->email);
        $tampered = preg_replace('/email=[^&]+/', 'email=attacker%40acme.test', $url);

        $this->get($tampered)->assertStatus(403);
    }

    public function test_resend_sends_the_branded_email_and_starts_the_cooldown(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $url = TenantVerificationUrl::resendFor($this->tenant, $user->email);

        $this->assertNotNull($url);

        $this->post($url)
            ->assertRedirect()
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, TenantEmailVerification::class);
        $this->assertGreaterThan(
            0,
            TenantVerificationCooldown::remaining($this->tenant, $user->email)
        );

        // A second request inside the server-side cooldown must not send again.
        $this->post($url)
            ->assertRedirect()
            ->assertSessionHas('status', 'verification-cooldown');

        Notification::assertSentTimes(TenantEmailVerification::class, 1);
    }

    public function test_resend_requires_a_valid_signature(): void
    {
        $user = User::factory()->unverified()->create();

        $url = (string) TenantVerificationUrl::resendFor($this->tenant, $user->email);
        $unsigned = preg_replace('/&signature=[^&]+/', '', $url);

        $this->post($unsigned)->assertStatus(403);
    }

    public function test_resend_before_the_owner_exists_reports_preparing(): void
    {
        $url = TenantVerificationUrl::resendFor($this->tenant, 'missing@acme.test');

        $this->assertNotNull($url);

        $this->post($url)
            ->assertRedirect()
            ->assertSessionHas('status', 'verification-preparing');
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );
    }
}
