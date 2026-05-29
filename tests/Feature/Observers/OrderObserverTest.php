<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantResourceUsage;
use App\Observers\Tenant\OrderObserver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderObserverTest extends TestCase
{
    protected Tenant $tenant;

    /** @var array<int, Tenant> */
    protected array $createdTenants = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        $this->createdTenants[] = $this->tenant;
        $this->initializeTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        $this->forgetTenant();

        // Manual cleanup: delete usage records and tenants, then drop databases.
        // We cannot rely on DatabaseTransactions because CREATE/DROP DATABASE are
        // DDL statements that auto-commit MySQL transactions.
        $tenantIds = [];
        foreach ($this->createdTenants as $t) {
            $tenantIds[] = $t->id;
            try {
                $dbName = $t->database()->getName();
                DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
            } catch (\Exception $e) {
                // Silently ignore — the DB may already be gone or inaccessible
            }
        }

        if ($tenantIds !== []) {
            TenantResourceUsage::whereIn('tenant_id', $tenantIds)->delete();
            Tenant::whereIn('id', $tenantIds)->delete();
        }

        parent::tearDown();
    }

    // -------------------------------------------------------------------
    //  Created event
    // -------------------------------------------------------------------

    public function test_creating_order_increments_orders_count(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->forCustomer($customer)->create();

        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('orders_count')
        );
    }

    public function test_multiple_orders_created_produce_correct_count(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->count(3)->forCustomer($customer)->create();

        $this->assertEquals(
            3,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('orders_count')
        );
    }

    // -------------------------------------------------------------------
    //  Deleted event
    // -------------------------------------------------------------------

    public function test_deleting_order_decrements_orders_count(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->forCustomer($customer)->create();
        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('orders_count')
        );

        $order->delete();

        $this->assertEquals(
            0,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('orders_count')
        );
    }

    public function test_multiple_creates_and_deletes_produce_correct_final_count(): void
    {
        $customer = Customer::factory()->create();
        $orders = Order::factory()->count(3)->forCustomer($customer)->create();
        $this->assertEquals(
            3,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('orders_count')
        );

        $orders[0]->delete();
        $this->assertEquals(
            2,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('orders_count')
        );

        $orders[1]->delete();
        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('orders_count')
        );

        $orders[2]->delete();
        $this->assertEquals(
            0,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('orders_count')
        );
    }

    // -------------------------------------------------------------------
    //  No-op in central context
    // -------------------------------------------------------------------

    public function test_created_does_nothing_outside_tenant_context(): void
    {
        $this->forgetTenant();
        $this->assertNull(tenant('id'));

        // Use new instance instead of factory to avoid creating tenant-related
        // models (Customer) on the central DB which lacks tenant tables.
        $order = new Order(['id' => 999, 'customer_id' => 1, 'total' => 100]);
        $order->exists = true;

        $observer = new OrderObserver;
        $observer->created($order);

        $this->assertEquals(0, TenantResourceUsage::count());
    }

    public function test_deleted_does_nothing_outside_tenant_context(): void
    {
        $this->forgetTenant();
        $this->assertNull(tenant('id'));

        // Use new instance instead of factory to avoid creating tenant-related
        // models (Customer) on the central DB which lacks tenant tables.
        $order = new Order(['id' => 999, 'customer_id' => 1, 'total' => 100]);
        $order->exists = true;

        $observer = new OrderObserver;
        $observer->deleted($order);

        $this->assertEquals(0, TenantResourceUsage::count());
    }

    // -------------------------------------------------------------------
    //  Tenant isolation
    // -------------------------------------------------------------------

    public function test_creating_order_in_tenant_a_does_not_affect_tenant_b(): void
    {
        $tenantA = $this->tenant;

        $customerA = Customer::factory()->create();
        Order::factory()->forCustomer($customerA)->create();

        $this->forgetTenant();

        $tenantB = $this->createTestTenant();
        $this->createdTenants[] = $tenantB;
        $this->initializeTenant($tenantB);

        $customerB = Customer::factory()->create();
        Order::factory()->count(2)->forCustomer($customerB)->create();

        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $tenantA->id)->value('orders_count'),
            'Tenant A should have 1 order'
        );
        $this->assertEquals(
            2,
            TenantResourceUsage::where('tenant_id', $tenantB->id)->value('orders_count'),
            'Tenant B should have 2 orders'
        );
    }

    public function test_delete_order_in_tenant_b_does_not_affect_tenant_a(): void
    {
        $customerA = Customer::factory()->create();
        Order::factory()->forCustomer($customerA)->create();
        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('orders_count')
        );

        $this->forgetTenant();

        $tenantB = $this->createTestTenant();
        $this->createdTenants[] = $tenantB;
        $this->initializeTenant($tenantB);

        $customerB = Customer::factory()->create();
        $orderB = Order::factory()->forCustomer($customerB)->create();
        $orderB->delete();

        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('orders_count'),
            'Tenant A order count should be unchanged'
        );
        $this->assertEquals(
            0,
            TenantResourceUsage::where('tenant_id', $tenantB->id)->value('orders_count'),
            'Tenant B order count should be 0'
        );
    }

    // -------------------------------------------------------------------
    //  Observer registration
    // -------------------------------------------------------------------

    public function test_order_observer_is_registered(): void
    {
        $this->assertTrue(
            Order::getEventDispatcher()->hasListeners('eloquent.created: '.Order::class),
            'Order model should have a listener for the created event'
        );

        $customer = Customer::factory()->create();
        Order::factory()->forCustomer($customer)->create();

        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('orders_count')
        );
    }

    // -------------------------------------------------------------------
    //  Rapid successive operations
    // -------------------------------------------------------------------

    public function test_rapid_successive_creates_and_deletes_produce_correct_count(): void
    {
        $customer = Customer::factory()->create();

        $o1 = Order::factory()->forCustomer($customer)->create();
        $o2 = Order::factory()->forCustomer($customer)->create();
        $o1->delete();
        $o3 = Order::factory()->forCustomer($customer)->create();
        $o2->delete();

        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('orders_count')
        );
    }
}
