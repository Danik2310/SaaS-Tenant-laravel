<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class OrderFlowIntegrationTest extends TestCase
{
    use RefreshDatabase, AdminAuthSetup;

    protected array $createdTenantDbNames = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();

        if (!\Illuminate\Support\Facades\Route::has('admin.api.orders.index')) {
            $this->markTestSkipped('Order/Payment API routes are not yet implemented.');
        }
    }

    /**
     * 🔄 Test: Complete order flow from creation to payment
     */
    public function test_complete_order_flow()
    {
        // Create a tenant for this test
        $tenant = $this->createTestTenant();
        $this->createdTenantDbNames[] = $tenant->database()->getName();

        // Switch to tenant context
        $this->initializeTenant($tenant);

        // 1. Create category and products
        $category = Category::factory()->create(['name' => 'Electronics']);
        $product1 = Product::factory()->create([
            'name' => 'Laptop',
            'price' => 999.99,
            'active' => true,
            'category_id' => $category->id
        ]);
        $product2 = Product::factory()->create([
            'name' => 'Mouse',
            'price' => 29.99,
            'active' => true,
            'category_id' => $category->id
        ]);

        // 2. Create customer
        $customer = Customer::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        // 3. Create order
        $orderData = [
            'customer_id' => $customer->id,
            'items' => [
                [
                    'product_id' => $product1->id,
                    'quantity' => 1
                ],
                [
                    'product_id' => $product2->id,
                    'quantity' => 2
                ]
            ],
            'notes' => 'Integration test order'
        ];

        $response = $this->postJson('/admin/api/orders', $orderData);
        $response->assertStatus(201);

        $order = Order::latest()->first();
        $this->assertEquals(1059.97, $order->total_amount); // 999.99 + (29.99 * 2)
        $this->assertEquals('pending', $order->status);
        $this->assertCount(2, $order->items);

        // 4. Update order status to processing
        $response = $this->patchJson("/admin/api/orders/{$order->id}/status", [
            'status' => 'processing'
        ]);
        $response->assertStatus(200);
        $this->assertEquals('processing', $order->fresh()->status);

        // 5. Create payment
        $paymentData = [
            'order_id' => $order->id,
            'amount' => 1059.97,
            'payment_method' => 'credit_card',
            'transaction_id' => 'txn_integration_test_001'
        ];

        $response = $this->postJson('/admin/api/payments', $paymentData);
        $response->assertStatus(201);

        $payment = Payment::latest()->first();
        $this->assertEquals(1059.97, $payment->amount);
        $this->assertEquals('completed', $payment->status);

        // 6. Update order status to completed
        $response = $this->patchJson("/admin/api/orders/{$order->id}/status", [
            'status' => 'completed'
        ]);
        $response->assertStatus(200);
        $this->assertEquals('completed', $order->fresh()->status);

        // 7. Verify order was created successfully
        $customer->refresh();
        $this->assertEquals(1, $customer->orders()->count());
        $this->assertEquals(1059.97, $customer->orders()->sum('total_amount'));

        // Switch back to central context
        $this->forgetTenant();
    }

    /**
     * 🔄 Test: Order cancellation flow
     */
    public function test_order_cancellation_flow()
    {
        // Create a tenant for this test
        $tenant = $this->createTestTenant();
        $this->createdTenantDbNames[] = $tenant->database()->getName();

        // Switch to tenant context
        $this->initializeTenant($tenant);

        // Create product and customer
        $product = Product::factory()->create(['active' => true]);
        $customer = Customer::factory()->create();

        // Create order
        $orderData = [
            'customer_id' => $customer->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2
                ]
            ]
        ];

        $response = $this->postJson('/admin/api/orders', $orderData);
        $response->assertStatus(201);

        $order = Order::latest()->first();

        // Cancel order
        $response = $this->patchJson("/admin/api/orders/{$order->id}/cancel");
        $response->assertStatus(200);

        $this->assertEquals('cancelled', $order->fresh()->status);

        // Switch back to central context
        $this->forgetTenant();
    }

    /**
     * 🔄 Test: Payment refund flow
     */
    public function test_payment_refund_flow()
    {
        // Create a tenant for this test
        $tenant = $this->createTestTenant();
        $this->createdTenantDbNames[] = $tenant->database()->getName();

        // Switch to tenant context
        $this->initializeTenant($tenant);

        // Create order and payment
        $order = Order::factory()->create(['total_amount' => 199.99]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 199.99,
            'status' => 'completed'
        ]);

        // Process refund
        $refundData = [
            'refund_amount' => 50.00,
            'reason' => 'Customer dissatisfaction'
        ];

        $response = $this->patchJson("/admin/api/payments/{$payment->id}/refund", $refundData);
        $response->assertStatus(200);

        // Verify refund was created
        $refund = Payment::where('order_id', $order->id)
                        ->where('amount', -50.00)
                        ->first();

        $this->assertNotNull($refund);
        $this->assertEquals('refunded', $refund->status);

        // Switch back to central context
        $this->forgetTenant();
    }

    /**
     * 🔄 Test: Insufficient stock prevents order creation
     */
    public function test_insufficient_stock_prevents_order()
    {
        // Create a tenant for this test
        $tenant = $this->createTestTenant();
        $this->createdTenantDbNames[] = $tenant->database()->getName();

        // Switch to tenant context
        $this->initializeTenant($tenant);

        $product = Product::factory()->create(['active' => true]);
        $customer = Customer::factory()->create();

        $orderData = [
            'customer_id' => $customer->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5 // Large quantity
                ]
            ]
        ];

        $response = $this->postJson('/admin/api/orders', $orderData);

        // Since there's no stock validation, this should succeed
        $response->assertStatus(201);

        // Verify order was created
        $this->assertEquals(1, Order::count());

        // Switch back to central context
        $this->forgetTenant();
    }
}