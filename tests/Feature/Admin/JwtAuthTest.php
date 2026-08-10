<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
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
        return app('tymon.jwt.manager')
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
        return $this->withCredentials()->withUnencryptedCookie('token', $token);
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

        $token = JWTAuth::fromUser($admin);
        JWTAuth::setToken($token)->invalidate();

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
}
