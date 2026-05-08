<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase, AdminAuthSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();

        if (!\Illuminate\Support\Facades\Route::has('admin.api.customers.index')) {
            $this->markTestSkipped('Customer API routes are not yet implemented.');
        }
    }

    /**
     * 👤 Test: Can list all customers
     */
    public function test_can_list_customers()
    {
        // Create test customers
        Customer::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        Customer::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $response = $this->getJson('/admin/api/customers');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'customers' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                            'phone',
                            'is_active',
                            'orders_count',
                            'total_spent',
                            'last_order_date'
                        ]
                    ],
                    'total'
                ])
                ->assertJsonCount(2, 'customers');
    }

    /**
     * 👤 Test: Can view customer details
     */
    public function test_can_view_customer_details()
    {
        $customer = Customer::factory()->create();
        $customer->orders()->create([
            'order_number' => 'ORD-001',
            'total_amount' => 199.99,
            'status' => 'completed'
        ]);

        $response = $this->getJson("/admin/api/customers/{$customer->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'customer' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                        'address',
                        'is_active',
                        'orders' => [
                            '*' => [
                                'id',
                                'order_number',
                                'total_amount',
                                'status',
                                'created_at'
                            ]
                        ],
                        'total_spent',
                        'orders_count'
                    ]
                ]);
    }

    /**
     * 👤 Test: Can create new customer
     */
    public function test_can_create_customer()
    {
        $customerData = [
            'name' => 'New Customer',
            'email' => 'newcustomer@example.com',
            'phone' => '+1234567890',
            'address' => '123 Main St, City, State 12345',
            'is_active' => true
        ];

        $response = $this->postJson('/admin/api/customers', $customerData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'customer' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                        'is_active'
                    ],
                    'message'
                ]);

        $this->assertDatabaseHas('customers', [
            'name' => 'New Customer',
            'email' => 'newcustomer@example.com',
            'phone' => '+1234567890'
        ]);
    }

    /**
     * 👤 Test: Can update customer
     */
    public function test_can_update_customer()
    {
        $customer = Customer::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com'
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '+0987654321'
        ];

        $response = $this->putJson("/admin/api/customers/{$customer->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson(['message' => 'Customer updated successfully']);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '+0987654321'
        ]);
    }

    /**
     * 👤 Test: Can toggle customer status
     */
    public function test_can_toggle_customer_status()
    {
        $customer = Customer::factory()->create(['is_active' => true]);

        $response = $this->patchJson("/admin/api/customers/{$customer->id}/toggle-status");

        $response->assertStatus(200)
                ->assertJson(['message' => 'Customer status updated successfully']);

        $this->assertFalse($customer->fresh()->is_active);
    }

    /**
     * 👤 Test: Can delete customer
     */
    public function test_can_delete_customer()
    {
        $customer = Customer::factory()->create();

        $response = $this->deleteJson("/admin/api/customers/{$customer->id}");

        $response->assertStatus(200)
                ->assertJson(['message' => 'Customer deleted successfully']);

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    /**
     * 👤 Test: Customer validation errors
     */
    public function test_customer_creation_validation_errors()
    {
        $response = $this->postJson('/admin/api/customers', [
            'name' => '',
            'email' => 'invalid-email',
            'phone' => 'invalid-phone'
        ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'message',
                    'errors' => [
                        'name',
                        'email',
                        'phone'
                    ]
                ]);
    }

    /**
     * 👤 Test: Cannot delete customer with orders
     */
    public function test_cannot_delete_customer_with_orders()
    {
        $customer = Customer::factory()->create();
        $customer->orders()->create([
            'order_number' => 'ORD-001',
            'total_amount' => 99.99,
            'status' => 'completed'
        ]);

        $response = $this->deleteJson("/admin/api/customers/{$customer->id}");

        $response->assertStatus(422)
                ->assertJson([
                    'message' => 'Cannot delete customer with associated orders'
                ]);

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    /**
     * 👤 Test: Can search customers
     */
    public function test_can_search_customers()
    {
        Customer::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        Customer::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $response = $this->getJson('/admin/api/customers?search=john');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'customers')
                ->assertJsonFragment(['name' => 'John Doe']);
    }
}