<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BillingUpgradeTest extends TestCase
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

    public function test_upgrade_page_excludes_trial_plan(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/billing/upgrade');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Tenant/Billing/Upgrade'));

        $page = $response->viewData('page');
        $props = is_array($page) ? $page['props'] : json_decode((string) $page, true)['props'];
        $slugs = collect($props['plans']['data'])->pluck('slug')->all();

        $this->assertContains('free', $slugs);
        $this->assertNotContains('trial', $slugs);
    }
}
