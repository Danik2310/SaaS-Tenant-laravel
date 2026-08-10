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
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_token_for_another_tenant_is_rejected(): void
    {
        $user = User::factory()->create();

        $token = JWTAuth::customClaims(['ten' => 'tenant-that-is-not-current'])
            ->fromUser($user);

        $this->withUnencryptedCookie('token', $token)
            ->get('/dashboard')
            ->assertStatus(401);
    }
}
