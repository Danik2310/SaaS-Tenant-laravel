<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class SubscriptionPaymentTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Model tests
    // ────────────────────────────────────────────────────────────────────────────

    public function test_subscription_payment_belongs_to_subscription(): void
    {
        $payment = SubscriptionPayment::factory()->create();

        $this->assertInstanceOf(Subscription::class, $payment->subscription);
    }

    public function test_subscription_payment_belongs_to_tenant(): void
    {
        $payment = SubscriptionPayment::factory()->create();

        $this->assertInstanceOf(Tenant::class, $payment->tenant);
    }

    public function test_subscription_has_payments_relationship(): void
    {
        $subscription = Subscription::factory()->create();
        SubscriptionPayment::factory()->count(3)->create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
        ]);

        $this->assertCount(3, $subscription->payments);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // API: index
    // ────────────────────────────────────────────────────────────────────────────

    public function test_index_returns_payments_for_subscription(): void
    {
        $subscription = Subscription::factory()->create();
        SubscriptionPayment::factory()->count(2)->create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
        ]);

        $response = $this->getJson("/admin/api/subscriptions/{$subscription->id}/payments");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'payments')
            ->assertJsonStructure([
                'payments' => [['id', 'subscription_id', 'tenant_id', 'amount', 'method', 'status', 'paid_at']],
                'subscription' => ['id', 'tenant_id', 'tenant_name', 'plan_name', 'plan_price'],
            ]);
    }

    public function test_index_returns_empty_when_no_payments(): void
    {
        $subscription = Subscription::factory()->create();

        $response = $this->getJson("/admin/api/subscriptions/{$subscription->id}/payments");

        $response->assertStatus(200)
            ->assertJsonCount(0, 'payments');
    }

    public function test_index_returns_404_for_nonexistent_subscription(): void
    {
        $response = $this->getJson('/admin/api/subscriptions/99999/payments');

        $response->assertStatus(404);
    }

    public function test_index_orders_payments_by_paid_at_desc(): void
    {
        $subscription = Subscription::factory()->create();
        $older = SubscriptionPayment::factory()->create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'paid_at' => now()->subDays(10),
        ]);
        $newer = SubscriptionPayment::factory()->create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'paid_at' => now()->subDays(1),
        ]);

        $response = $this->getJson("/admin/api/subscriptions/{$subscription->id}/payments");

        $payments = $response->json('payments');
        $this->assertEquals($newer->id, $payments[0]['id']);
        $this->assertEquals($older->id, $payments[1]['id']);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // API: store
    // ────────────────────────────────────────────────────────────────────────────

    public function test_store_creates_payment(): void
    {
        $subscription = Subscription::factory()->create();

        $response = $this->postJson("/admin/api/subscriptions/{$subscription->id}/payments", [
            'amount' => 49.99,
            'method' => 'stripe',
            'reference' => 'txn_abc123',
            'status' => 'completed',
            'paid_at' => '2026-07-15',
            'notes' => 'Monthly payment',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Payment recorded successfully');

        $this->assertDatabaseHas('subscription_payments', [
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'amount' => 49.99,
            'method' => 'stripe',
            'status' => 'completed',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $subscription = Subscription::factory()->create();

        $response = $this->postJson("/admin/api/subscriptions/{$subscription->id}/payments", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'method', 'status', 'paid_at']);
    }

    public function test_store_validates_method_enum(): void
    {
        $subscription = Subscription::factory()->create();

        $response = $this->postJson("/admin/api/subscriptions/{$subscription->id}/payments", [
            'amount' => 49.99,
            'method' => 'bitcoin',
            'status' => 'completed',
            'paid_at' => '2026-07-15',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['method']);
    }

    public function test_store_validates_status_enum(): void
    {
        $subscription = Subscription::factory()->create();

        $response = $this->postJson("/admin/api/subscriptions/{$subscription->id}/payments", [
            'amount' => 49.99,
            'method' => 'stripe',
            'status' => 'invalid',
            'paid_at' => '2026-07-15',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_store_validates_amount_min(): void
    {
        $subscription = Subscription::factory()->create();

        $response = $this->postJson("/admin/api/subscriptions/{$subscription->id}/payments", [
            'amount' => 0,
            'method' => 'stripe',
            'status' => 'completed',
            'paid_at' => '2026-07-15',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_store_returns_404_for_nonexistent_subscription(): void
    {
        $response = $this->postJson('/admin/api/subscriptions/99999/payments', [
            'amount' => 49.99,
            'method' => 'stripe',
            'status' => 'completed',
            'paid_at' => '2026-07-15',
        ]);

        $response->assertStatus(404);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Auth
    // ────────────────────────────────────────────────────────────────────────────

    public function test_index_without_permission_returns_403(): void
    {
        $subscription = Subscription::factory()->create();
        $regularAdmin = AdminUser::factory()->create();
        $regularRole = Role::firstOrCreate(['name' => 'regular', 'guard_name' => 'admin']);
        $regularAdmin->assignRole('regular');
        $this->actingAs($regularAdmin, 'admin');

        $response = $this->getJson("/admin/api/subscriptions/{$subscription->id}/payments");

        $response->assertStatus(403);
    }

    public function test_store_without_permission_returns_403(): void
    {
        $subscription = Subscription::factory()->create();
        $regularAdmin = AdminUser::factory()->create();
        $regularRole = Role::firstOrCreate(['name' => 'regular', 'guard_name' => 'admin']);
        $regularAdmin->assignRole('regular');
        $this->actingAs($regularAdmin, 'admin');

        $response = $this->postJson("/admin/api/subscriptions/{$subscription->id}/payments", [
            'amount' => 49.99,
            'method' => 'stripe',
            'status' => 'completed',
            'paid_at' => '2026-07-15',
        ]);

        $response->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        auth('admin')->logout();
        $subscription = Subscription::factory()->create();

        $response = $this->getJson("/admin/api/subscriptions/{$subscription->id}/payments");

        $response->assertStatus(401);
    }
}
