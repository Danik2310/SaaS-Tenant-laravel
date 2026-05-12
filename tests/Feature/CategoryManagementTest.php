<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();

        if (! Route::has('admin.api.categories.index')) {
            $this->markTestSkipped('Category API routes are not yet implemented.');
        }
    }

    /**
     * 📂 Test: Can list all categories
     */
    public function test_can_list_categories()
    {
        // Create test categories
        Category::factory()->create(['name' => 'Electronics', 'description' => 'Electronic devices']);
        Category::factory()->create(['name' => 'Clothing', 'description' => 'Fashion items']);

        $response = $this->getJson('/admin/api/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'categories' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'is_active',
                        'products_count',
                    ],
                ],
                'total',
            ])
            ->assertJsonCount(2, 'categories');
    }

    /**
     * 📂 Test: Can create new category
     */
    public function test_can_create_category()
    {
        $categoryData = [
            'name' => 'New Category',
            'description' => 'Category description',
            'is_active' => true,
        ];

        $response = $this->postJson('/admin/api/categories', $categoryData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'category' => [
                    'id',
                    'name',
                    'description',
                    'is_active',
                ],
                'message',
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'New Category',
            'description' => 'Category description',
            'is_active' => true,
        ]);
    }

    /**
     * 📂 Test: Can update category
     */
    public function test_can_update_category()
    {
        $category = Category::factory()->create([
            'name' => 'Old Category',
            'description' => 'Old description',
        ]);

        $updateData = [
            'name' => 'Updated Category',
            'description' => 'Updated description',
            'is_active' => false,
        ];

        $response = $this->putJson("/admin/api/categories/{$category->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Category updated successfully']);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Category',
            'description' => 'Updated description',
            'is_active' => false,
        ]);
    }

    /**
     * 📂 Test: Can toggle category status
     */
    public function test_can_toggle_category_status()
    {
        $category = Category::factory()->create(['is_active' => true]);

        $response = $this->patchJson("/admin/api/categories/{$category->id}/toggle-status");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Category status updated successfully']);

        $this->assertFalse($category->fresh()->is_active);
    }

    /**
     * 📂 Test: Can delete category
     */
    public function test_can_delete_category()
    {
        $category = Category::factory()->create();

        $response = $this->deleteJson("/admin/api/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Category deleted successfully']);

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    /**
     * 📂 Test: Category validation errors
     */
    public function test_category_creation_validation_errors()
    {
        $response = $this->postJson('/admin/api/categories', [
            'name' => '',
            'description' => str_repeat('a', 1001), // Too long
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'name',
                    'description',
                ],
            ]);
    }

    /**
     * 📂 Test: Cannot delete category with products
     */
    public function test_cannot_delete_category_with_products()
    {
        $category = Category::factory()->create();
        $category->products()->create([
            'name' => 'Test Product',
            'description' => 'Test description',
            'price' => 99.99,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/admin/api/categories/{$category->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete category with associated products',
            ]);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }
}
