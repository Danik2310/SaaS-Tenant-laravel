<?php

namespace Tests\Feature\Tenant\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class JwtTenantScopeTest extends TestCase
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

    public function test_tenant_user_token_grants_access_to_tenant_dashboard(): void
    {
        $user = User::factory()->create();

        $token = Auth::guard('web')->login($user);

        $this->withUnencryptedCookie('token', $token)
            ->withAuthSession()
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_tenant_token_exposes_only_minimal_claims(): void
    {
        $user = User::factory()->create([
            'name' => 'Tenant Owner',
            'email' => 'owner@example.com',
        ]);

        $token = Auth::guard('web')->login($user);
        $claims = Auth::guard('web')->setToken($token)->getPayload()->toArray();

        $this->assertSame((string) $user->getKey(), (string) $claims['sub']);
        $this->assertSame((string) $this->tenant->id, (string) $claims['ten']);

        foreach (['email', 'name', 'password', 'roles', 'permissions'] as $claim) {
            $this->assertArrayNotHasKey($claim, $claims);
        }
    }

    public function test_token_for_another_tenant_is_rejected(): void
    {
        $user = User::factory()->create();

        $token = JWTAuth::customClaims(['ten' => 'tenant-that-is-not-current'])
            ->fromUser($user);

        $this->withUnencryptedCookie('token', $token)
            ->withAuthSession()
            ->get('/dashboard')
            ->assertStatus(401);
    }
}
