<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Impersonation;

use App\Models\Tenant;
use App\Shared\Support\ImpersonationToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EntryTest extends TestCase
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

    private function entryUrl(?array $payload = null, ?string $token = null): string
    {
        if ($token === null) {
            $payload ??= [
                'tenant_id' => $this->tenant->id,
                'admin_id' => '1',
                'admin_name' => 'Super Admin',
                'admin_email' => 'admin@example.com',
            ];
            $token = ImpersonationToken::sign($payload, (int) config('impersonation.token_ttl', 300));
        }

        return '/god-mode/enter?impersonate_token='.$token;
    }

    public function test_valid_token_enters_god_mode_and_redirects_to_dashboard(): void
    {
        $response = $this->get($this->entryUrl());

        $response->assertRedirect();
        $this->assertNotNull(session('impersonation'));
        $this->assertSame($this->tenant->id, session('impersonation')['tenant_id']);
    }

    public function test_missing_token_is_rejected(): void
    {
        $this->get('/god-mode/enter')->assertForbidden();
    }

    public function test_tampered_token_is_rejected(): void
    {
        $url = $this->entryUrl();
        $tampered = substr($url, 0, -2).'xx';
        $this->get($tampered)->assertForbidden();
    }

    public function test_token_for_different_tenant_is_rejected(): void
    {
        $url = $this->entryUrl(['tenant_id' => 'some-other-tenant', 'admin_name' => 'x']);
        $this->get($url)->assertForbidden();
    }

    public function test_expired_token_is_rejected(): void
    {
        $payload = [
            'tenant_id' => $this->tenant->id,
            'admin_name' => 'x',
        ];
        $token = ImpersonationToken::sign($payload, -10);
        $this->get($this->entryUrl(null, $token))->assertForbidden();
    }

    public function test_stop_exits_god_mode_and_redirects_to_central(): void
    {
        $this->withSession(['impersonation' => [
            'admin_id' => '1',
            'admin_name' => 'Super Admin',
            'tenant_id' => $this->tenant->id,
            'started_at' => time(),
            'ttl' => 60,
        ]])
            ->post('/god-mode/stop')
            ->assertRedirect();
    }
}
