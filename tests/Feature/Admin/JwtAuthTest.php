<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\User;
use App\Shared\Providers\JwtServiceProvider;
use App\Shared\Support\AuthSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Manager;
use PHPOpenSourceSaver\JWTAuth\Providers\JWT\Lcobucci;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class JwtAuthTest extends TestCase
{
    use RefreshDatabase;

    private function tokenCookie(TestResponse $response): Cookie
    {
        $cookie = collect($response->headers->getCookies())
            ->first(fn (Cookie $cookie) => $cookie->getName() === 'token');

        $this->assertNotNull($cookie, 'Expected a token cookie on the response.');

        return $cookie;
    }

    private function loginToken(AdminUser $admin): string
    {
        $response = $this->postJson('/central/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        return $this->tokenCookie($response)->getValue();
    }

    private function mintToken(AdminUser $admin, array $claims = []): string
    {
        // The package's default manager is bound to the `web` guard. Once the
        // admin guard has its own secret, admin tokens must be signed with it.
        $manager = new Manager(
            new Lcobucci(
                (string) config('jwt.guard_secrets.admin'),
                (string) config('jwt.algo'),
                config('jwt.keys')
            ),
            app('tymon.jwt.blacklist'),
            app('tymon.jwt.payload.factory')
        );

        return $manager
            ->encode(
                app('tymon.jwt.payload.factory')
                    ->setRefreshFlow(true)
                    ->customClaims(array_merge([
                        'sub' => $admin->getJWTIdentifier(),
                        'prv' => sha1(AdminUser::class),
                    ], $claims))
                    ->make()
            )
            ->get();
    }

    /**
     * Send the token as an unencrypted, credentialed cookie.
     *
     * The token cookie is excluded from EncryptCookies, so the test framework
     * must not encrypt it, and JSON requests must opt in via withCredentials
     * (getJson drops cookies otherwise).
     */
    private function withTokenCookie(string $token): static
    {
        return $this->withCredentials()
            ->withUnencryptedCookie('token', $token)
            ->withAuthSession();
    }

    public function test_login_sets_http_only_token_cookie(): void
    {
        $admin = AdminUser::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/central/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $cookie = $this->tokenCookie($response);

        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->getValue() !== '');
    }

    public function test_token_cookie_authenticates_auth_probe(): void
    {
        $admin = AdminUser::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $token = $this->loginToken($admin);

        $this->withTokenCookie($token)
            ->getJson('/admin/user')
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@example.com');
    }

    public function test_token_cookie_grants_access_to_protected_admin_routes(): void
    {
        $admin = AdminUser::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $token = $this->loginToken($admin);

        $this->withTokenCookie($token)
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withTokenCookie('not-a-valid-jwt')
            ->getJson('/admin/api/tenants')
            ->assertStatus(401);
    }

    public function test_expired_token_within_refresh_window_is_renewed(): void
    {
        $admin = AdminUser::factory()->create();

        $token = $this->mintToken($admin, [
            'exp' => now()->subMinute()->timestamp,
        ]);

        $response = $this->withTokenCookie($token)
            ->getJson('/admin/user');

        $response->assertOk()->assertJsonPath('user.email', $admin->email);

        $newCookie = $this->tokenCookie($response);

        $this->assertNotSame($token, $newCookie->getValue());
    }

    public function test_token_beyond_refresh_window_is_rejected(): void
    {
        $admin = AdminUser::factory()->create();

        $validator = app('tymon.jwt.validators.payload');
        $validator->setRefreshTTL(60 * 24 * 30);

        $token = $this->mintToken($admin, [
            'iat' => now()->subDays(8)->timestamp,
            'exp' => now()->subDays(8)->addMinutes((int) config('jwt.ttl'))->timestamp,
        ]);

        $validator->setRefreshTTL((int) config('jwt.refresh_ttl'));

        $this->withTokenCookie($token)
            ->getJson('/admin/api/tenants')
            ->assertStatus(401);
    }

    public function test_blacklisted_token_is_rejected(): void
    {
        $admin = AdminUser::factory()->create();

        $token = Auth::guard('admin')->login($admin);
        Auth::guard('admin')->setToken($token)->invalidate();
        Auth::forgetGuards();

        $this->withTokenCookie($token)
            ->getJson('/admin/api/tenants')
            ->assertStatus(401);
    }

    public function test_web_user_token_is_rejected_by_admin_guard(): void
    {
        $user = User::factory()->create();

        $token = Auth::guard('web')->login($user);

        $this->withTokenCookie($token)
            ->getJson('/admin/api/tenants')
            ->assertStatus(401);
    }

    public function test_admin_login_works_with_dedicated_secret(): void
    {
        config(['jwt.guard_secrets.admin' => 'admin-only-secret-0123456789abcdef']);

        $admin = AdminUser::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/central/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $token = $this->tokenCookie($response)->getValue();

        $this->withTokenCookie($token)
            ->getJson('/admin/user')
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@example.com');
    }

    public function test_admin_guard_rejects_web_tokens_when_admin_has_dedicated_secret(): void
    {
        config(['jwt.guard_secrets.admin' => 'admin-only-secret-0123456789abcdef']);

        $user = User::factory()->create();

        $token = Auth::guard('web')->login($user);

        $this->withTokenCookie($token)
            ->getJson('/admin/api/tenants')
            ->assertStatus(401);
    }

    public function test_production_rejects_missing_jwt_secret(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('the web secret is missing');

        JwtServiceProvider::assertSecureConfiguration('web', null, 'HS256', true);
    }

    public function test_production_rejects_short_hmac_secret(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('at least 32 bytes');

        JwtServiceProvider::assertSecureConfiguration('web', 'too-short', 'HS256', true);
    }

    public function test_production_rejects_shared_tenant_and_admin_secrets(): void
    {
        $secret = 'a-very-long-tenant-secret-0123456789abcdef';
        config(['jwt.guard_secrets.web' => $secret]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must be distinct');

        JwtServiceProvider::assertSecureConfiguration('admin', $secret, 'HS256', true);
    }

    public function test_disallowed_algorithm_is_rejected_even_outside_production(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not allowed');

        JwtServiceProvider::assertSecureConfiguration('web', str_repeat('a', 64), 'none', false);
    }

    public function test_admin_token_contains_no_sensitive_claims(): void
    {
        $admin = AdminUser::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $token = $this->loginToken($admin);
        $claims = Auth::guard('admin')->setToken($token)->getPayload()->toArray();

        $this->assertSame((string) $admin->getKey(), (string) $claims['sub']);
        $this->assertSame([], $admin->getJWTCustomClaims());

        foreach (['email', 'name', 'password', 'roles', 'permissions'] as $claim) {
            $this->assertArrayNotHasKey($claim, $claims);
        }
    }

    public function test_refresh_blacklists_the_previous_token(): void
    {
        $admin = AdminUser::factory()->create();
        $token = $this->mintToken($admin, [
            'exp' => now()->subMinute()->timestamp,
        ]);

        $response = $this->withTokenCookie($token)
            ->getJson('/admin/user')
            ->assertOk();

        $refreshedToken = $this->tokenCookie($response)->getValue();

        $this->assertNotSame($token, $refreshedToken);

        // The refresh resolved and cached the admin user; reset the guards so
        // the replay request is evaluated from the cookie alone.
        Auth::forgetGuards();

        $this->withTokenCookie($token)
            ->getJson('/admin/api/tenants')
            ->assertStatus(401);

        Auth::forgetGuards();

        $this->withTokenCookie($refreshedToken)
            ->getJson('/admin/user')
            ->assertOk();
    }

    public function test_logout_blacklists_token(): void
    {
        $admin = AdminUser::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $token = $this->loginToken($admin);

        $this->withTokenCookie($token)
            ->postJson('/central/logout')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->withTokenCookie($token)
            ->getJson('/admin/api/tenants')
            ->assertStatus(401);
    }

    public function test_fresh_admin_login_carries_the_session_anchors(): void
    {
        $admin = AdminUser::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $token = $this->loginToken($admin);

        $this->assertNotNull(session(AuthSession::STARTED_AT));
        $this->assertNotNull(session(AuthSession::LAST_ACTIVITY));

        // The login response wrote the anchors, so a raw token cookie is
        // accepted without the test seeding them.
        $this->withCredentials()
            ->withUnencryptedCookie('token', $token)
            ->getJson('/admin/user')
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@example.com');
    }

    public function test_idle_admin_session_is_revoked(): void
    {
        $admin = AdminUser::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $token = $this->loginToken($admin);

        $this->travel(31)->minutes();

        $this->withCredentials()
            ->withUnencryptedCookie('token', $token)
            ->withAuthSession(
                now()->subMinutes(31)->timestamp,
                now()->subMinutes(31)->timestamp,
            )
            ->getJson('/admin/user')
            ->assertStatus(401)
            ->assertCookieExpired('token');
    }
}
