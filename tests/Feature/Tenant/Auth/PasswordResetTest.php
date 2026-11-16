<?php

namespace Tests\Feature\Tenant\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Satisfies the reset rule: min 8, mixed case, a number, and a symbol.
     */
    private const STRONG_PASSWORD = 'Str0ng!Passw0rd';

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

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => self::STRONG_PASSWORD,
                'password_confirmation' => self::STRONG_PASSWORD,
            ]);

            $response->assertSessionHasNoErrors();
            $response->assertRedirect(route('login'));
            $response->assertSessionHas(
                'status',
                'Your password has been updated. You can now log in.'
            );

            return true;
        });

        $this->assertTrue(
            Hash::check(self::STRONG_PASSWORD, $user->fresh()->password),
            'The stored password should match the one submitted to the reset form.'
        );
    }

    public function test_password_must_meet_the_reset_policy(): void
    {
        // The reset route is throttled to three attempts a minute; six policy
        // probes in one test would otherwise trip the 429 before the assertions.
        $this->withoutMiddleware(ThrottleRequests::class);

        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $weakPasswords = [
                'new-password',   // min 8 only: the old rule
                'nouppercase1!',  // no uppercase letter
                'NOLOWERCASE1!',  // no lowercase letter
                'NoNumber!',      // no number
                'NoSymbol12',     // no symbol
                'Sh0rt!',         // under 8 characters
            ];

            foreach ($weakPasswords as $password) {
                $this->post('/reset-password', [
                    'token' => $notification->token,
                    'email' => $user->email,
                    'password' => $password,
                    'password_confirmation' => $password,
                ])->assertSessionHasErrors('password');
            }

            return true;
        });

        $this->assertTrue(
            Hash::check('password', $user->fresh()->password),
            'A rejected password must not be written to the user.'
        );
    }

    public function test_password_confirmation_must_match(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => self::STRONG_PASSWORD,
                'password_confirmation' => 'Different!Passw0rd',
            ]);

            $response->assertSessionHasErrors('password');

            return true;
        });

        $this->assertTrue(
            Hash::check('password', $user->fresh()->password),
            'A mismatched confirmation must not be written to the user.'
        );
    }
}
