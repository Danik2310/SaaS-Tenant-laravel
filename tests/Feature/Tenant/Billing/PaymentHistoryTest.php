<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Billing;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentHistoryTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/billing/payments')->assertRedirect();
    }

    public function test_lists_only_the_current_tenant_payments(): void
    {
        $user = User::factory()->create();
        $own = $this->createPaymentFor($this->tenant);
        $this->createPaymentFor($this->foreignTenant());

        $response = $this->actingAs($user)->get('/billing/payments');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Billing/Payments', false)
                ->has('payments.data', 1)
                ->where('payments.data.0.id', $own->id)
                ->where('payments.data.0.tenant_id', $this->tenant->id)
                ->where('payments.data.0.plan_name', $own->plan_name)
                ->where('payments.data.0.currency', 'USD'));
    }

    public function test_filters_payments_by_status(): void
    {
        $user = User::factory()->create();
        $this->createPaymentFor($this->tenant, ['status' => 'completed']);
        $pending = $this->createPaymentFor($this->tenant, ['status' => 'pending']);

        $response = $this->actingAs($user)->get('/billing/payments?status=pending');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Billing/Payments', false)
                ->has('payments.data', 1)
                ->where('payments.data.0.id', $pending->id)
                ->where('payments.data.0.status', 'pending')
                ->where('filters.status', 'pending'));
    }

    public function test_new_payment_invalidates_the_cached_history(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/billing/payments')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Billing/Payments', false)
                ->has('payments.data', 0));

        $payment = $this->createPaymentFor($this->tenant);

        $this->actingAs($user)->get('/billing/payments')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Billing/Payments', false)
                ->has('payments.data', 1)
                ->where('payments.data.0.id', $payment->id));
    }

    /**
     * Builds a payment directly (bypassing SubscriptionPaymentFactory, whose
     * definition() creates its own Subscription + Tenant and would not be FK
     * valid while tenancy is initialized).
     */
    private function createPaymentFor(Tenant $tenant, array $overrides = []): SubscriptionPayment
    {
        $subscription = Subscription::factory()->create(['tenant_id' => $tenant->id]);

        $payment = new SubscriptionPayment(array_merge([
            'subscription_id' => $subscription->id,
            'tenant_id' => $tenant->id,
            'amount' => 29.99,
            'method' => 'stripe',
            'reference' => 'pi_test_'.Str::uuid(),
            'status' => 'completed',
            'paid_at' => now(),
        ], $overrides));
        $payment->save();

        return $payment;
    }

    /**
     * A tenant row used purely to satisfy the FK on subscription_payments.
     * Created on the central connection with Eloquent so the model's
     * generateReferenceId logic keeps the unique reference_id (and the partial
     * unique email index) collision-free across a multi-file shard.
     */
    private function foreignTenant(): Tenant
    {
        return Tenant::withoutEvents(fn () => Tenant::factory()->create());
    }
}
