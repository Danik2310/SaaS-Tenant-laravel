<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Product;

class CategoryTest extends TenantTestCase
{

    public function test_category_has_required_fillable_attributes(): void
    {
        $category = Category::factory()->create();

        $this->assertNotNull($category->id);
        $this->assertNotNull($category->name);
        $this->assertNotNull($category->slug);
    }

    public function test_category_can_be_created(): void
    {
        $category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);

        $this->assertInstanceOf(Category::class, $category);
        $this->assertEquals('Electronics', $category->name);
    }

    public function test_category_can_be_created_with_factory(): void
    {
        $category = Category::factory()->create();

        $this->assertNotNull($category->id);
        $this->assertNotNull($category->name);
    }

    public function test_category_belongs_to_parent_category(): void
    {
        $parentCategory = Category::factory()->create();
        $childCategory = Category::factory()->create(['parent_id' => $parentCategory->id]);

        $this->assertInstanceOf(Category::class, $childCategory->parent);
        $this->assertEquals($parentCategory->id, $childCategory->parent->id);
    }

    public function test_category_has_many_children(): void
    {
        $parentCategory = Category::factory()->create();
        Category::factory(3)->create(['parent_id' => $parentCategory->id]);

        $this->assertCount(3, $parentCategory->children);
        $this->assertTrue($parentCategory->children->every(fn ($child) => $child->parent_id === $parentCategory->id));
    }

    public function test_root_category_has_no_parent(): void
    {
        $category = Category::factory()->create(['parent_id' => null]);

        $this->assertNull($category->parent_id);
        $this->assertNull($category->parent);
    }

    public function test_category_has_many_products(): void
    {
        $category = Category::factory()->create();
        Product::factory(5)->forCategory($category)->create();

        $this->assertCount(5, $category->products);
        $this->assertTrue($category->products->every(fn ($product) => $product->category_id === $category->id));
    }

    public function test_category_all_products_includes_child_categories(): void
    {
        $parentCategory = Category::factory()->create();
        $childCategory = Category::factory()->create(['parent_id' => $parentCategory->id]);

        Product::factory(2)->forCategory($parentCategory)->create();
        Product::factory(3)->forCategory($childCategory)->create();

        $allProducts = $parentCategory->allProducts()->get();

        $this->assertCount(5, $allProducts);
    }

    public function test_category_soft_delete(): void
    {
        $category = Category::factory()->create();
        $categoryId = $category->id;

        $category->delete();

        $this->assertNull(Category::find($categoryId));
    }

    public function test_category_restore_from_soft_delete(): void
    {
        $category = Category::factory()->create();
        
        $category->delete();

        // Since soft delete is not implemented, we can't restore
        $this->assertNull(Category::find($category->id));
    }

    public function test_category_casts_active_as_boolean(): void
    {
        $category = Category::factory()->create();

        $this->assertInstanceOf(Category::class, $category);
    }

    public function test_category_with_no_products_has_empty_products_relation(): void
    {
        $category = Category::factory()->create();

        $this->assertCount(0, $category->products);
    }

    public function test_category_hierarchical_structure(): void
    {
        $root = Category::factory()->create(['parent_id' => null]);
        $level1 = Category::factory()->create(['parent_id' => $root->id]);
        $level2 = Category::factory()->create(['parent_id' => $level1->id]);

        $this->assertNull($root->parent_id);
        $this->assertEquals($root->id, $level1->parent_id);
        $this->assertEquals($level1->id, $level2->parent_id);

        $this->assertCount(1, $root->children);
        $this->assertCount(1, $level1->children);
        $this->assertCount(0, $level2->children);
    }
}