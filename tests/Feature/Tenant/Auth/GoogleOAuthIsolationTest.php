<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Host isolation for the two halves of the Google flow.
 *
 * The flow only works because the central callback and the tenant handoff live
 * on *different* hosts. These tests pin that boundary: the central endpoints
 * must be unreachable from a tenant host (they are not per-tenant routes), and
 * the signed handoff must be unreachable from a central host and unusable on a
 * different tenant's domain.
 */
class GoogleOAuthIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, Tenant> */
    private array $tenants = [];

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->createTestTenant();
        $this->tenants[] = $tenant;
        $this->initializeTenant($tenant);
    }

    protected function tearDown(): void
    {
        $this->forgetTenant();

        foreach ($this->tenants as $tenant) {
            DB::statement("DROP DATABASE IF EXISTS `{$tenant->database()->getName()}`");
        }

        parent::tearDown();
    }

    public function test_central_oauth_endpoints_are_not_reachable_from_a_tenant_host(): void
    {
        $domain = $this->tenants[0]->domain;

        $this->get('http://'.$domain.'/auth/google/redirect?tenant='.$this->tenants[0]->id)
            ->assertNotFound();

        $this->get('http://'.$domain.'/auth/google/callback?code=fake-code')
            ->assertNotFound();
    }

    public function test_handoff_is_not_reachable_from_a_central_host(): void
    {
        $user = User::factory()->create();

        $url = URL::temporarySignedRoute('auth.google.complete', now()->addMinute(), ['user' => $user->id]);

        $this->get('http://localhost/auth/google/complete?'.parse_url($url, PHP_URL_QUERY))
            ->assertNotFound();
    }

    public function test_handoff_signature_is_bound_to_the_tenant_host(): void
    {
        $user = User::factory()->create();

        $url = URL::temporarySignedRoute('auth.google.complete', now()->addMinute(), ['user' => $user->id]);

        $otherTenant = $this->createTestTenant();
        $this->tenants[] = $otherTenant;

        // Same path, same signature, different tenant host → invalid signature.
        $this->get('http://'.$otherTenant->domain.'/auth/google/complete?'.parse_url($url, PHP_URL_QUERY))
            ->assertForbidden();
    }
}
