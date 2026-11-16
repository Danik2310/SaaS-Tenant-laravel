<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The central `/login` workspace finder.
 *
 * Tenant hosts keep the real login under the same URI, so the first case here
 * is as much a regression guard as the resolution cases: adding a central
 * `/login` must not shadow the tenant route.
 */
class WorkspaceLookupTest extends TestCase
{
    use RefreshDatabase;

    private const CENTRAL = 'http://localhost';

    private Tenant $tenant;

    private string $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        $this->domain = (string) $this->tenant->domain;
    }

    protected function tearDown(): void
    {
        $this->forgetTenant();

        if (isset($this->tenant)) {
            DB::statement("DROP DATABASE IF EXISTS `{$this->tenant->database()->getName()}`");
        }

        parent::tearDown();
    }

    private function loginUrl(?string $domain = null): string
    {
        return 'http://'.($domain ?? $this->domain).'/login';
    }

    private function lookup(string $workspace)
    {
        return $this->post(self::CENTRAL.'/find-workspace', ['workspace' => $workspace]);
    }

    // ──────────────────────────────────────────────
    //  Central page
    // ──────────────────────────────────────────────

    public function test_central_login_renders_the_workspace_finder(): void
    {
        $this->get(self::CENTRAL.'/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/FindWorkspace')
                ->where('tenant_domain_suffix', 'sasapp'));
    }

    public function test_tenant_hosts_keep_the_real_login(): void
    {
        $this->get($this->loginUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    // ──────────────────────────────────────────────
    //  Resolution
    // ──────────────────────────────────────────────

    public function test_a_bare_slug_resolves_to_the_workspace_login(): void
    {
        $slug = strstr($this->domain, '.', true);

        $this->lookup($slug)->assertRedirect($this->loginUrl());
    }

    public function test_a_full_host_is_case_insensitive(): void
    {
        $this->lookup(strtoupper($this->domain))->assertRedirect($this->loginUrl());
    }

    public function test_a_pasted_url_is_reduced_to_its_host(): void
    {
        $this->lookup('https://'.$this->domain.'/login?next=/dashboard')
            ->assertRedirect($this->loginUrl());
    }

    public function test_a_development_port_is_ignored(): void
    {
        $this->lookup($this->domain.':8000')->assertRedirect($this->loginUrl());
    }

    public function test_a_trailing_dot_is_ignored(): void
    {
        $this->lookup($this->domain.'.')->assertRedirect($this->loginUrl());
    }

    public function test_a_custom_domain_redirects_to_the_address_that_was_typed(): void
    {
        Domain::create([
            'tenant_id' => $this->tenant->id,
            'domain' => 'inventory.acme.test',
            'is_primary' => false,
        ]);

        $this->lookup('inventory.acme.test')
            ->assertRedirect($this->loginUrl('inventory.acme.test'));
    }

    public function test_a_leading_www_is_retried_without_it(): void
    {
        Domain::create([
            'tenant_id' => $this->tenant->id,
            'domain' => 'shop.acme.test',
            'is_primary' => false,
        ]);

        $this->lookup('www.shop.acme.test')
            ->assertRedirect($this->loginUrl('shop.acme.test'));
    }

    public function test_inertia_visits_receive_a_location_header(): void
    {
        $this->withHeaders(['X-Inertia' => 'true'])
            ->lookup($this->domain)
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', $this->loginUrl());
    }

    // ──────────────────────────────────────────────
    //  Rejections
    // ──────────────────────────────────────────────

    public function test_an_unknown_workspace_is_rejected_inline(): void
    {
        $this->lookup('does-not-exist-anywhere')
            ->assertSessionHasErrors('workspace');
    }

    public function test_a_central_domain_is_never_a_workspace(): void
    {
        $this->lookup('localhost')
            ->assertSessionHasErrors('workspace');
    }

    public function test_input_that_is_not_an_address_is_rejected(): void
    {
        $this->lookup('https://')
            ->assertSessionHasErrors('workspace');
    }

    public function test_the_lookup_is_not_reachable_from_a_tenant_host(): void
    {
        $this->post('http://'.$this->domain.'/find-workspace', ['workspace' => $this->domain])
            ->assertNotFound();
    }

    public function test_bulk_lookups_are_throttled(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->lookup('does-not-exist-'.$attempt)->assertSessionHasErrors('workspace');
        }

        $this->lookup('does-not-exist-final')->assertStatus(429);
    }
}
