<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Order;

class CustomerTest extends TenantTestCase
{
    public function test_customer_has_required_fillable_attributes(): void
    {
        $fillable = ['name', 'email', 'phone', 'document', 'notes', 'active'];
        $this->assertEquals($fillable, (new Customer)->getFillable());
    }

    public function test_customer_can_be_created(): void
    {
        $customer = Customer::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '123456789',
            'document' => '12345678900',
            'active' => true,
        ]);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('John Doe', $customer->name);
        $this->assertEquals('john@example.com', $customer->email);
        $this->assertTrue($customer->active);
    }

    public function test_customer_can_be_created_with_factory(): void
    {
        $customer = Customer::factory()->create();

        $this->assertNotNull($customer->id);
        $this->assertNotNull($customer->name);
        $this->assertNotNull($customer->email);
    }

    public function test_customer_has_many_orders(): void
    {
        $customer = Customer::factory()->create();
        Order::factory(3)->forCustomer($customer)->create();

        $this->assertCount(3, $customer->orders);
        $this->assertTrue($customer->orders->every(fn ($order) => $order->customer_id === $customer->id));
    }

    public function test_customer_active_orders_excludes_cancelled(): void
    {
        $customer = Customer::factory()->create();
        Order::factory(2)->forCustomer($customer)->paid()->create();
        Order::factory(1)->forCustomer($customer)->state(['status' => 'cancelled'])->create();

        $activeOrders = $customer->activeOrders()->get();

        $this->assertCount(2, $activeOrders);
        $this->assertTrue($activeOrders->every(fn ($order) => $order->status !== 'cancelled'));
    }

    public function test_customer_total_spent_only_counts_paid_orders(): void
    {
        $customer = Customer::factory()->create();

        $order1 = Order::factory()->forCustomer($customer)->paid()->state(['total' => 1000])->create();
        $order2 = Order::factory()->forCustomer($customer)->paid()->state(['total' => 2000])->create();
        Order::factory()->forCustomer($customer)->pending()->state(['total' => 500])->create();

        $totalSpent = $customer->totalSpent();

        $this->assertEquals(3000, $totalSpent);
    }

    public function test_customer_soft_delete(): void
    {
        $customer = Customer::factory()->create();
        $customerId = $customer->id;

        $customer->delete();

        $this->assertSoftDeleted($customer);
        $this->assertNull(Customer::find($customerId));
        $this->assertNotNull(Customer::withTrashed()->find($customerId));
    }

    public function test_customer_restore_from_soft_delete(): void
    {
        $customer = Customer::factory()->create();

        $customer->delete();
        $customer->restore();

        $this->assertNotNull(Customer::find($customer->id));
        $this->assertNotSoftDeleted($customer);
    }

    public function test_customer_casts_active_as_boolean(): void
    {
        $customer = Customer::factory()->create(['active' => 1]);

        $this->assertIsBool($customer->active);
        $this->assertTrue($customer->active);
    }

    public function test_customer_casts_timestamps_as_datetime(): void
    {
        $customer = Customer::factory()->create();

        $this->assertNotNull($customer->created_at);
        $this->assertNotNull($customer->updated_at);
        $this->assertInstanceOf(\DateTime::class, $customer->created_at);
        $this->assertInstanceOf(\DateTime::class, $customer->updated_at);
    }

    public function test_customer_with_no_orders_has_zero_total_spent(): void
    {
        $customer = Customer::factory()->create();

        $this->assertEquals(0, $customer->totalSpent());
    }
}
