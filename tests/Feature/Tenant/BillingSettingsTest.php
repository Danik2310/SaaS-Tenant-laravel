<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Shared\Constants\PermissionNames;
use App\Shared\Contracts\PaymentGatewayInterface;
use Database\Seeders\TenantUserRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BillingSettingsTest extends TestCase
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

    public function test_settings_index_exposes_billing_card_to_billing_users(): void
    {
        $user = $this->billingUser();

        $this->actingAs($user)->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Settings/Index')
                ->where('canManageBilling', true));
    }

    public function test_billing_page_shows_only_masked_card_metadata(): void
    {
        $user = $this->billingUser();
        $this->tenant->forceFill(['stripe_customer_id' => 'cus_test'])->save();

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('listPaymentMethods')
            ->once()
            ->with('cus_test')
            ->andReturn([[
                'id' => 'pm_test',
                'brand' => 'Visa',
                'last4' => '4242',
                'exp_month' => 12,
                'exp_year' => 2030,
                'is_default' => true,
            ]]);
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        $this->actingAs($user)->get('/settings/billing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Settings/Billing')
                ->where('billing.billing_email', $this->tenant->email)
                ->where('cards.0.last4', '4242')
                ->where('cards.0.is_default', true)
                ->missing('cards.0.number')
                ->missing('cards.0.cvc'));
    }

    public function test_billing_page_exposes_subscription_summary(): void
    {
        $user = $this->billingUser();
        $plan = Plan::factory()->create(['name' => 'Pro Plan', 'slug' => 'pro-summary']);
        $startsAt = now()->subMonth();
        $endsAt = now()->addMonth();
        Subscription::createForTenant($this->tenant, $plan, 'active', $endsAt, $startsAt);

        $this->actingAs($user)->get('/settings/billing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Settings/Billing')
                ->where('subscription.plan_name', 'Pro Plan')
                ->where('subscription.status', 'active')
                ->where('subscription.starts_at', $startsAt->toFormattedDateString())
                ->where('subscription.ends_at', $endsAt->toFormattedDateString()));
    }

    public function test_user_without_manage_payments_is_forbidden(): void
    {
        $this->seed(TenantUserRolePermissionSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/settings/billing')->assertForbidden();
    }

    public function test_billing_details_are_saved_on_the_central_tenant(): void
    {
        $user = $this->billingUser();
        $ownerEmail = $this->tenant->email;

        $this->actingAs($user)->put('/settings/billing', [
            'billing_email' => 'billing@example.com',
            'company_name' => 'Acme Ltd',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'phone' => '+1 555 0100',
            'address_line1' => '1 Main Street',
            'address_line2' => 'Suite 2',
            'city' => 'London',
            'state' => 'London',
            'postal_code' => 'SW1A 1AA',
            'country' => 'United Kingdom',
            'tax_id' => 'GB123456789',
        ])->assertSessionHas('status', 'Billing details updated.');

        $this->tenant->refresh();

        $this->assertSame('billing@example.com', $this->tenant->billing_email);
        $this->assertSame('Acme Ltd', $this->tenant->company_name);
        $this->assertSame('GB123456789', $this->tenant->tax_id);
        $this->assertSame($ownerEmail, $this->tenant->email, 'The owner login email must not change.');
    }

    public function test_portal_creates_a_stripe_customer_then_redirects_to_the_hosted_portal(): void
    {
        config()->set('services.stripe.secret_key', 'sk_test_123');
        $user = $this->billingUser();

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('createCustomer')
            ->once()
            ->with(Mockery::on(fn (array $params): bool => $params['email'] === $this->tenant->email))
            ->andReturn(['id' => 'cus_new']);
        $gateway->shouldReceive('createCustomerPortalSession')
            ->once()
            ->with('cus_new', Mockery::on(fn (array $params): bool => str_contains($params['return_url'], '/settings/billing')))
            ->andReturn([
                'id' => 'bps_test',
                'url' => 'https://billing.stripe.com/p/session/test',
            ]);
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        $this->actingAs($user)->post('/settings/billing/portal')
            ->assertRedirect('https://billing.stripe.com/p/session/test');

        $this->assertSame('cus_new', $this->tenant->refresh()->stripe_customer_id);
    }

    /**
     * A tenant user holding the `manage payments` permission required by the
     * billing routes.
     */
    private function billingUser(): User
    {
        $this->seed(TenantUserRolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(PermissionNames::ROLE_TENANT_ADMIN);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }
}
