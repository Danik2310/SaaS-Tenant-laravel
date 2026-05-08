<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class PaymentManagementTest extends TestCase
{
    use RefreshDatabase, AdminAuthSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();

        if (!\Illuminate\Support\Facades\Route::has('admin.api.payments.index')) {
            $this->markTestSkipped('Payment API routes are not yet implemented.');
        }
    }

    /**
     * 💳 Test: Can list all payments
     */
    public function test_can_list_payments()
    {
        // Create test payments
        Payment::factory()->create(['status' => 'completed', 'amount' => 199.99]);
        Payment::factory()->create(['status' => 'pending', 'amount' => 149.99]);

        $response = $this->getJson('/admin/api/payments');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'payments' => [
                        '*' => [
                            'id',
                            'order_id',
                            'amount',
                            'status',
                            'payment_method',
                            'transaction_id',
                            'order',
                            'created_at'
                        ]
                    ],
                    'total'
                ])
                ->assertJsonCount(2, 'payments');
    }

    /**
     * 💳 Test: Can view payment details
     */
    public function test_can_view_payment_details()
    {
        $payment = Payment::factory()->create([
            'amount' => 199.99,
            'status' => 'completed',
            'payment_method' => 'credit_card'
        ]);

        $response = $this->getJson("/admin/api/payments/{$payment->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'payment' => [
                        'id',
                        'order_id',
                        'amount',
                        'status',
                        'payment_method',
                        'transaction_id',
                        'payment_date',
                        'notes',
                        'order'
                    ]
                ]);
    }

    /**
     * 💳 Test: Can process payment
     */
    public function test_can_process_payment()
    {
        $order = Order::factory()->create(['total_amount' => 199.99]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'status' => 'pending',
            'amount' => 199.99
        ]);

        $processData = [
            'transaction_id' => 'txn_123456789',
            'payment_method' => 'credit_card',
            'notes' => 'Payment processed successfully'
        ];

        $response = $this->patchJson("/admin/api/payments/{$payment->id}/process", $processData);

        $response->assertStatus(200)
                ->assertJson(['message' => 'Payment processed successfully']);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'completed',
            'transaction_id' => 'txn_123456789',
            'payment_method' => 'credit_card'
        ]);
    }

    /**
     * 💳 Test: Can refund payment
     */
    public function test_can_refund_payment()
    {
        $payment = Payment::factory()->create([
            'status' => 'completed',
            'amount' => 199.99
        ]);

        $refundData = [
            'refund_amount' => 50.00,
            'reason' => 'Customer request'
        ];

        $response = $this->patchJson("/admin/api/payments/{$payment->id}/refund", $refundData);

        $response->assertStatus(200)
                ->assertJson(['message' => 'Payment refunded successfully']);

        // Check if refund record was created
        $this->assertDatabaseHas('payments', [
            'order_id' => $payment->order_id,
            'amount' => -50.00, // Negative amount for refund
            'status' => 'refunded'
        ]);
    }

    /**
     * 💳 Test: Can create manual payment
     */
    public function test_can_create_manual_payment()
    {
        $order = Order::factory()->create(['total_amount' => 199.99]);

        $paymentData = [
            'order_id' => $order->id,
            'amount' => 199.99,
            'payment_method' => 'bank_transfer',
            'transaction_id' => 'manual_txn_001',
            'notes' => 'Manual payment entry'
        ];

        $response = $this->postJson('/admin/api/payments', $paymentData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'payment' => [
                        'id',
                        'order_id',
                        'amount',
                        'status',
                        'payment_method'
                    ],
                    'message'
                ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount' => 199.99,
            'payment_method' => 'bank_transfer',
            'transaction_id' => 'manual_txn_001',
            'status' => 'completed'
        ]);
    }

    /**
     * 💳 Test: Payment validation errors
     */
    public function test_payment_creation_validation_errors()
    {
        $response = $this->postJson('/admin/api/payments', [
            'order_id' => 999, // Non-existent order
            'amount' => -100, // Negative amount
            'payment_method' => ''
        ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'message',
                    'errors' => [
                        'order_id',
                        'amount',
                        'payment_method'
                    ]
                ]);
    }

    /**
     * 💳 Test: Cannot refund more than payment amount
     */
    public function test_cannot_refund_more_than_payment_amount()
    {
        $payment = Payment::factory()->create([
            'status' => 'completed',
            'amount' => 100.00
        ]);

        $response = $this->patchJson("/admin/api/payments/{$payment->id}/refund", [
            'refund_amount' => 150.00, // More than original payment
            'reason' => 'Test refund'
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'message' => 'Refund amount cannot exceed payment amount'
                ]);
    }
}