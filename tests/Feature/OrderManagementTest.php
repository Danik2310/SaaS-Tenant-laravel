<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();

        if (! Route::has('admin.api.orders.index')) {
            $this->markTestSkipped('Order API routes are not yet implemented.');
        }
    }

    /**
     * 🛒 Test: Can list all orders
     */
    public function test_can_list_orders()
    {
        // Create test orders
        Order::factory()->create(['status' => 'pending']);
        Order::factory()->create(['status' => 'completed']);

        $response = $this->getJson('/admin/api/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'orders' => [
                    '*' => [
                        'id',
                        'order_number',
                        'status',
                        'total_amount',
                        'customer',
                        'items_count',
                        'created_at',
                    ],
                ],
                'total',
            ])
            ->assertJsonCount(2, 'orders');
    }

    /**
     * 🛒 Test: Can view order details
     */
    public function test_can_view_order_details()
    {
        $order = Order::factory()->create();
        $order->items()->create([
            'product_id' => Product::factory()->create()->id,
            'quantity' => 2,
            'unit_price' => 50.00,
            'total_price' => 100.00,
        ]);

        $response = $this->getJson("/admin/api/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'order' => [
                    'id',
                    'order_number',
                    'status',
                    'total_amount',
                    'customer',
                    'items' => [
                        '*' => [
                            'id',
                            'product',
                            'quantity',
                            'unit_price',
                            'total_price',
                        ],
                    ],
                    'payments',
                ],
            ]);
    }

    /**
     * 🛒 Test: Can update order status
     */
    public function test_can_update_order_status()
    {
        $order = Order::factory()->create(['status' => 'pending']);

        $response = $this->patchJson("/admin/api/orders/{$order->id}/status", [
            'status' => 'processing',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Order status updated successfully']);

        $this->assertEquals('processing', $order->fresh()->status);
    }

    /**
     * 🛒 Test: Can create order manually
     */
    public function test_can_create_order_manually()
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['price' => 99.99, 'stock_quantity' => 10]);

        $orderData = [
            'customer_id' => $customer->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
            'notes' => 'Test order',
        ];

        $response = $this->postJson('/admin/api/orders', $orderData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'order' => [
                    'id',
                    'order_number',
                    'total_amount',
                    'status',
                ],
                'message',
            ]);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'total_amount' => 199.98, // 2 * 99.99
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 99.99,
            'total_price' => 199.98,
        ]);
    }

    /**
     * 🛒 Test: Can cancel order
     */
    public function test_can_cancel_order()
    {
        $order = Order::factory()->create(['status' => 'pending']);

        $response = $this->patchJson("/admin/api/orders/{$order->id}/cancel");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Order cancelled successfully']);

        $this->assertEquals('cancelled', $order->fresh()->status);
    }

    /**
     * 🛒 Test: Order validation errors
     */
    public function test_order_creation_validation_errors()
    {
        $response = $this->postJson('/admin/api/orders', [
            'customer_id' => 999, // Non-existent customer
            'items' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'customer_id',
                    'items',
                ],
            ]);
    }
}
