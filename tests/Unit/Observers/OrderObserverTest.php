<?php

namespace Tests\Unit\Observers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\TenantResourceUsage;
use App\Observers\Tenant\OrderObserver;

class OrderObserverTest extends ObserverTestCase
{
    // -----------------------------------------------------------------------
    //  Created event
    // -----------------------------------------------------------------------

    public function test_order_created_increments_orders_count(): void
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

    // -----------------------------------------------------------------------
    //  Deleted event
    // -----------------------------------------------------------------------

    public function test_order_deleted_decrements_orders_count(): void
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

    public function test_create_and_delete_orders_produces_correct_final_count(): void
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

    // -----------------------------------------------------------------------
    //  No-op in central context
    // -----------------------------------------------------------------------

    public function test_created_does_nothing_when_tenant_id_is_null(): void
    {
        $this->forgetTenant();
        $this->assertNull(tenant('id'));

        $order = Order::factory()->make(['id' => 999]);

        $observer = new OrderObserver;
        $observer->created($order);

        $this->assertEquals(0, TenantResourceUsage::count());
    }

    public function test_deleted_does_nothing_when_tenant_id_is_null(): void
    {
        $this->forgetTenant();
        $this->assertNull(tenant('id'));

        $order = Order::factory()->make(['id' => 999]);

        $observer = new OrderObserver;
        $observer->deleted($order);

        $this->assertEquals(0, TenantResourceUsage::count());
    }

    // -----------------------------------------------------------------------
    //  Tenant isolation
    // -----------------------------------------------------------------------

    public function test_creating_order_in_tenant_a_does_not_affect_tenant_b(): void
    {
        $tenantA = $this->tenant;

        $customerA = Customer::factory()->create();
        Order::factory()->forCustomer($customerA)->create();

        $this->forgetTenant();

        $tenantB = $this->createTestTenant();
        $this->createdDatabases[] = $tenantB->database()->getName();
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
        $this->createdDatabases[] = $tenantB->database()->getName();
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

    // -----------------------------------------------------------------------
    //  Observer registration
    // -----------------------------------------------------------------------

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

    // -----------------------------------------------------------------------
    //  Rapid successive operations
    // -----------------------------------------------------------------------

    public function test_rapid_successive_creates_and_deletes_produce_correct_count(): void
    {
        $customer = Customer::factory()->create();

        $o1 = Order::factory()->forCustomer($customer)->create();
        $o2 = Order::factory()->forCustomer($customer)->create();
        $o1->delete();
        $o3 = Order::factory()->forCustomer($customer)->create();
        $o2->delete();

        // o3 is the only remaining order
        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('orders_count')
        );
    }
}
