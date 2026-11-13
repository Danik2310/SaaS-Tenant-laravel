<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers `GET /api/v1/user`.
 *
 * The endpoint moved from the Sanctum guard to the `admin` JWT guard, so
 * Sanctum personal access tokens are now rejected and the middleware rejects
 * every non-admin principal before the route closure runs. The historical
 * class name is kept so the explicit CI shard assignment stays unchanged.
 */
class SanctumApiTokenTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(AdminUser $admin): string
    {
        return auth('admin')->login($admin);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/user');

        $response->assertStatus(401);
    }

    public function test_admin_jwt_can_access_user_endpoint(): void
    {
        $admin = AdminUser::factory()->create([
            'name' => 'API User',
            'email' => 'api@example.com',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken($admin))
            ->getJson('/api/v1/user');

        $response->assertStatus(200)
            ->assertJsonStructure(['user' => ['id', 'name', 'email']])
            ->assertJsonPath('user.email', 'api@example.com');
    }

    public function test_admin_jwt_cookie_can_access_user_endpoint(): void
    {
        $admin = AdminUser::factory()->create();

        $response = $this->withCookie(config('jwt.cookie_key_name', 'token'), $this->adminToken($admin))
            ->getJson('/api/v1/user');

        $response->assertStatus(200)
            ->assertJsonPath('user.id', $admin->id);
    }

    public function test_invalid_token_returns_401(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/v1/user');

        $response->assertStatus(401);
    }

    public function test_tenant_jwt_is_rejected(): void
    {
        $tenantUser = (new User)->forceFill([
            'id' => 1,
            'name' => 'Tenant User',
            'email' => 'tenant@example.com',
        ]);

        $tenantToken = auth('web')->login($tenantUser);

        $response = $this->withHeader('Authorization', 'Bearer '.$tenantToken)
            ->getJson('/api/v1/user');

        $response->assertStatus(401);
    }

    public function test_sanctum_personal_access_token_is_rejected(): void
    {
        $admin = AdminUser::factory()->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user');

        $response->assertStatus(401);
    }

    public function test_revoked_token_cannot_access_endpoint(): void
    {
        $admin = AdminUser::factory()->create();
        $token = $this->adminToken($admin);

        auth('admin')->logout();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user');

        $response->assertStatus(401);
    }
}
