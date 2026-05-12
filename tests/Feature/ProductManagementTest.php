<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();

        if (! Route::has('admin.api.products.index')) {
            $this->markTestSkipped('Product API routes are not yet implemented.');
        }
    }

    /**
     * 📦 Test: Can list all products
     */
    public function test_can_list_products()
    {
        // Create test products
        Product::factory()->create(['name' => 'Test Product 1', 'price' => 99.99]);
        Product::factory()->create(['name' => 'Test Product 2', 'price' => 149.99]);

        $response = $this->getJson('/admin/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'products' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'price',
                        'stock_quantity',
                        'is_active',
                        'category',
                        'images',
                    ],
                ],
                'total',
            ])
            ->assertJsonCount(2, 'products');
    }

    /**
     * 📦 Test: Can create new product
     */
    public function test_can_create_product()
    {
        $category = Category::factory()->create();

        $productData = [
            'name' => 'New Product',
            'description' => 'Product description',
            'price' => 199.99,
            'stock_quantity' => 50,
            'category_id' => $category->id,
            'is_active' => true,
        ];

        $response = $this->postJson('/admin/api/products', $productData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'product' => [
                    'id',
                    'name',
                    'description',
                    'price',
                    'stock_quantity',
                    'is_active',
                ],
                'message',
            ]);

        $this->assertDatabaseHas('products', [
            'name' => 'New Product',
            'price' => 199.99,
            'stock_quantity' => 50,
        ]);
    }

    /**
     * 📦 Test: Can update product
     */
    public function test_can_update_product()
    {
        $product = Product::factory()->create([
            'name' => 'Old Product',
            'price' => 99.99,
        ]);

        $updateData = [
            'name' => 'Updated Product',
            'price' => 149.99,
            'stock_quantity' => 75,
        ];

        $response = $this->putJson("/admin/api/products/{$product->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Product updated successfully']);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product',
            'price' => 149.99,
            'stock_quantity' => 75,
        ]);
    }

    /**
     * 📦 Test: Can upload product images
     */
    public function test_can_upload_product_images()
    {
        $product = Product::factory()->create();

        $image = UploadedFile::fake()->image('product.jpg');

        $response = $this->postJson("/admin/api/products/{$product->id}/images", [
            'images' => [$image],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'images' => [
                    '*' => [
                        'id',
                        'path',
                        'url',
                    ],
                ],
                'message',
            ]);
    }

    /**
     * 📦 Test: Can toggle product status
     */
    public function test_can_toggle_product_status()
    {
        $product = Product::factory()->create(['is_active' => true]);

        $response = $this->patchJson("/admin/api/products/{$product->id}/toggle-status");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Product status updated successfully']);

        $this->assertFalse($product->fresh()->is_active);
    }

    /**
     * 📦 Test: Can delete product
     */
    public function test_can_delete_product()
    {
        $product = Product::factory()->create();

        $response = $this->deleteJson("/admin/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Product deleted successfully']);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    /**
     * 📦 Test: Product validation errors
     */
    public function test_product_creation_validation_errors()
    {
        $response = $this->postJson('/admin/api/products', [
            'name' => '',
            'price' => -10,
            'stock_quantity' => -5,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'name',
                    'price',
                    'stock_quantity',
                ],
            ]);
    }
}
