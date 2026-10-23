<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Plan;
use App\Models\Tenant;
use App\Tenants\Builders\TenantBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\TestCase;

class TenantBuilderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tenants are written through the default connection while subscriptions
     * use mysql_central. Keeping the default connection open in a transaction
     * would make the FK-checked subscription insert wait on the uncommitted
     * tenant row, so let these writes commit and rely on setUp()'s purge.
     */
    protected $connectionsToTransact = [];

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([TenantCreated::class]);
    }

    private function data(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Acme Corp',
            'email' => 'acme@example.com',
        ], $overrides);
    }

    public function test_build_without_data_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TenantBuilder)->build();
    }

    public function test_with_data_ignores_caller_supplied_status_id_and_trial_end(): void
    {
        $tenant = (new TenantBuilder)
            ->withData($this->data([
                'id' => 'HACKED',
                'status' => 'Suspended',
                'trial_ends_at' => now()->addYear(),
            ]))
            ->build();

        $this->assertNotSame('HACKED', $tenant->id);
        $this->assertSame('Active', $tenant->status);
        $this->assertNull($tenant->trial_ends_at);
    }

    public function test_with_domain_attaches_the_domain(): void
    {
        $tenant = (new TenantBuilder)
            ->withData($this->data())
            ->withDomain('acme.sasapp')
            ->build();

        $this->assertTrue($tenant->domains()->where('domain', 'acme.sasapp')->exists());
    }

    public function test_with_domain_rejects_a_domain_already_in_use(): void
    {
        Tenant::factory()->create(['id' => 'taken']);

        $this->expectException(InvalidArgumentException::class);

        (new TenantBuilder)
            ->withData($this->data())
            ->withDomain('taken.sasapp');
    }

    public function test_assert_domain_available_accepts_a_free_domain(): void
    {
        (new TenantBuilder)->assertDomainAvailable('free.sasapp');

        $this->addToAssertionCount(1);
    }

    public function test_resolve_assignable_plan_returns_null_without_a_slug(): void
    {
        $this->assertNull((new TenantBuilder)->resolveAssignablePlan(null));
    }

    public function test_resolve_assignable_plan_rejects_an_unknown_slug(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TenantBuilder)->resolveAssignablePlan('missing');
    }

    public function test_public_signup_cannot_take_an_inactive_plan(): void
    {
        Plan::factory()->create(['slug' => 'hidden', 'status' => 'inactive', 'price' => 0]);

        $this->expectException(InvalidArgumentException::class);

        (new TenantBuilder)->resolveAssignablePlan('hidden', publicSignup: true);
    }

    public function test_public_signup_cannot_take_a_paid_plan_without_payment(): void
    {
        Plan::factory()->create(['slug' => 'pro', 'status' => 'active', 'price' => 49]);

        $this->expectException(InvalidArgumentException::class);

        (new TenantBuilder)->resolveAssignablePlan('pro', publicSignup: true);
    }

    public function test_public_signup_can_take_a_paid_plan_with_confirmed_payment(): void
    {
        $plan = Plan::factory()->create(['slug' => 'pro', 'status' => 'active', 'price' => 49]);

        $resolved = (new TenantBuilder)->resolveAssignablePlan(
            'pro',
            publicSignup: true,
            confirmedPayment: true,
        );

        $this->assertSame($plan->id, $resolved?->id);
    }

    public function test_with_plan_enters_a_trial_through_the_state_machine(): void
    {
        Plan::factory()->create(['slug' => 'trial', 'status' => 'active', 'price' => 0]);

        $tenant = (new TenantBuilder)
            ->withData($this->data())
            ->withPlan('trial')
            ->build();

        $this->assertSame('Trial', $tenant->status);
        $this->assertNotNull($tenant->trial_ends_at);
        $this->assertTrue($tenant->trial_ends_at->isFuture());
    }

    public function test_for_retargets_the_builder(): void
    {
        $tenant = Tenant::factory()->create();

        $attached = (new TenantBuilder)
            ->for($tenant)
            ->withDomain('extra.sasapp')
            ->build();

        $this->assertSame($tenant->id, $attached->id);
        $this->assertTrue($tenant->domains()->where('domain', 'extra.sasapp')->exists());
    }
}
