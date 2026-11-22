<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\BillingCustomer;
use App\Models\CashierSubscription;
use App\Models\CashierSubscriptionItem;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Cashier\Cashier;
use Tests\TestCase;

class CashierIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_is_configured_for_tenant_billing_models(): void
    {
        $this->assertSame(BillingCustomer::class, Cashier::$customerModel);
        $this->assertSame(CashierSubscription::class, Cashier::$subscriptionModel);
        $this->assertSame(CashierSubscriptionItem::class, Cashier::$subscriptionItemModel);
        $this->assertFalse(Cashier::$registersRoutes);
    }

    public function test_find_billable_resolves_a_tenant_by_stripe_id(): void
    {
        $tenant = Tenant::factory()->create([
            'stripe_customer_id' => 'cus_cashier',
        ]);

        $billable = Cashier::findBillable('cus_cashier');

        $this->assertInstanceOf(BillingCustomer::class, $billable);
        $this->assertSame($tenant->id, $billable->id);
        $this->assertSame('cus_cashier', $billable->stripeId());
    }

    public function test_legacy_customer_id_and_cashier_stripe_id_stay_in_sync(): void
    {
        $tenant = Tenant::factory()->create([
            'stripe_customer_id' => 'cus_legacy',
        ]);

        $this->assertSame('cus_legacy', $tenant->fresh()->stripe_id);

        $billable = BillingCustomer::findOrFail($tenant->id);
        $billable->forceFill(['stripe_id' => 'cus_cashier'])->save();

        $this->assertSame('cus_cashier', $tenant->fresh()->stripe_customer_id);
    }

    public function test_cashier_models_use_central_connection_and_custom_tables(): void
    {
        $subscription = new CashierSubscription;
        $item = new CashierSubscriptionItem;

        $this->assertSame('mysql_central', $subscription->getConnectionName());
        $this->assertSame('cashier_subscriptions', $subscription->getTable());
        $this->assertSame('subscription_id', $subscription->getForeignKey());

        $this->assertSame('mysql_central', $item->getConnectionName());
        $this->assertSame('cashier_subscription_items', $item->getTable());
    }

    public function test_cashier_schema_is_available(): void
    {
        foreach (['stripe_id', 'pm_type', 'pm_last_four', 'registration_trial_ends_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('tenants', $column), "tenants.{$column} is missing");
        }

        $this->assertTrue(Schema::hasTable('cashier_subscriptions'));
        $this->assertTrue(Schema::hasColumn('cashier_subscriptions', 'billing_customer_id'));
        $this->assertTrue(Schema::hasTable('cashier_subscription_items'));
    }
}
