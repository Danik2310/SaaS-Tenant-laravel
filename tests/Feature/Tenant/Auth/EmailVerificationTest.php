<?php

namespace Tests\Feature\Tenant\Auth;

use App\Models\Tenant;
use App\Models\User;
use App\Tenants\Notifications\TenantEmailVerification;
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

        $this->get($verificationUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/EmailVerified')
                ->where('email', $user->email)
                ->where('alreadyVerified', false)
                ->has('loginUrl')
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

        $this->get($verificationUrl)->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        Event::fake();

        $this->get($verificationUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/EmailVerified')
                ->where('alreadyVerified', true)
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

        $this->get($url)->assertOk();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
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
