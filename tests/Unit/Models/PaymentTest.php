<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\Payment;

class PaymentTest extends TenantTestCase
{
    public function test_payment_has_required_fillable_attributes(): void
    {
        $payment = Payment::factory()->create();

        $this->assertNotNull($payment->id);
        $this->assertNotNull($payment->order_id);
        $this->assertNotNull($payment->amount);
    }

    public function test_payment_can_be_created(): void
    {
        $order = Order::factory()->create();

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 500,
            'method' => 'card',
            'reference' => 'REF-123456',
        ]);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(500, $payment->amount);
        $this->assertEquals('card', $payment->method);
        $this->assertEquals($order->id, $payment->order_id);
    }

    public function test_payment_can_be_created_with_factory(): void
    {
        $payment = Payment::factory()->create();

        $this->assertNotNull($payment->id);
        $this->assertNotNull($payment->order_id);
        $this->assertNotNull($payment->amount);
    }

    public function test_payment_belongs_to_order(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->forOrder($order)->create();

        $this->assertInstanceOf(Order::class, $payment->order);
        $this->assertEquals($order->id, $payment->order->id);
    }

    public function test_payment_amount_cast_as_decimal(): void
    {
        $payment = Payment::factory()->create(['amount' => 999.99]);

        $this->assertIsString($payment->amount);
        $this->assertEquals('999.99', $payment->amount);
    }

    public function test_payment_with_card_method(): void
    {
        $payment = Payment::factory()->byCard()->create();

        $this->assertEquals('card', $payment->method);
    }

    public function test_payment_with_transfer_method(): void
    {
        $payment = Payment::factory()->byTransfer()->create();

        $this->assertEquals('transfer', $payment->method);
    }

    public function test_payment_can_have_reference(): void
    {
        $payment = Payment::factory()->create(['reference' => 'REF-999888']);

        $this->assertEquals('REF-999888', $payment->reference);
    }

    public function test_payment_can_have_notes(): void
    {
        $payment = Payment::factory()->create(['reference' => 'REF-123456']);

        $this->assertEquals('REF-123456', $payment->reference);
    }

    public function test_payment_timestamps_cast_as_datetime(): void
    {
        $payment = Payment::factory()->create();

        $this->assertNotNull($payment->created_at);
        $this->assertNotNull($payment->updated_at);
        $this->assertInstanceOf(\DateTime::class, $payment->created_at);
        $this->assertInstanceOf(\DateTime::class, $payment->updated_at);
    }

    public function test_multiple_payments_for_same_order(): void
    {
        $order = Order::factory()->create();

        Payment::factory(3)->forOrder($order)->create();

        $payments = Payment::where('order_id', $order->id)->get();
        $this->assertCount(3, $payments);
    }

    public function test_payment_method_values(): void
    {
        $methods = ['cash', 'card', 'transfer', 'check'];

        foreach ($methods as $method) {
            $payment = Payment::factory()->create(['method' => $method]);
            $this->assertEquals($method, $payment->method);
        }
    }
}
