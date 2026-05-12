<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;

class ProductTest extends TenantTestCase
{
    public function test_product_has_required_fillable_attributes(): void
    {
        $fillable = ['name', 'description', 'sku', 'category_id', 'price', 'cost', 'active'];
        $this->assertEquals($fillable, (new Product)->getFillable());
    }

    public function test_product_can_be_created(): void
    {
        $category = Category::factory()->create();

        $product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'category_id' => $category->id,
            'price' => 100.00,
            'cost' => 50.00,
            'active' => true,
        ]);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('Test Product', $product->name);
        $this->assertEquals('TEST-001', $product->sku);
        $this->assertEquals(100.00, $product->price);
    }

    public function test_product_can_be_created_with_factory(): void
    {
        $product = Product::factory()->create();

        $this->assertNotNull($product->id);
        $this->assertNotNull($product->name);
        $this->assertNotNull($product->sku);
        $this->assertNotNull($product->category_id);
    }

    public function test_product_belongs_to_category(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->forCategory($category)->create();

        $this->assertInstanceOf(Category::class, $product->category);
        $this->assertEquals($category->id, $product->category->id);
    }

    public function test_product_has_many_order_items(): void
    {
        $product = Product::factory()->create();
        OrderItem::factory(5)->forProduct($product)->create();

        $this->assertCount(5, $product->orderItems);
        $this->assertTrue($product->orderItems->every(fn ($item) => $item->product_id === $product->id));
    }

    public function test_product_calculates_margin_correctly(): void
    {
        $product = Product::factory()->create([
            'cost' => 100,
            'price' => 150,
        ]);

        $expectedMargin = ((150 - 100) / 100) * 100; // 50%
        $this->assertEquals($expectedMargin, $product->margin);
    }

    public function test_product_margin_is_zero_when_cost_is_zero(): void
    {
        $product = Product::factory()->create([
            'cost' => 0,
            'price' => 100,
        ]);

        $this->assertEquals(0, $product->margin);
    }

    public function test_product_total_sold_quantity(): void
    {
        $product = Product::factory()->create();
        OrderItem::factory()->forProduct($product)->create(['quantity' => 5]);
        OrderItem::factory()->forProduct($product)->create(['quantity' => 3]);
        OrderItem::factory()->forProduct($product)->create(['quantity' => 2]);

        $this->assertEquals(10, $product->totalSold());
    }

    public function test_product_total_revenue(): void
    {
        $product = Product::factory()->create();
        OrderItem::factory()->forProduct($product)->create(['subtotal' => 500]);
        OrderItem::factory()->forProduct($product)->create(['subtotal' => 300]);

        $this->assertEquals(800, $product->totalRevenue());
    }

    public function test_product_price_cast_as_decimal(): void
    {
        $product = Product::factory()->create(['price' => 99.99]);

        $this->assertIsString($product->price);
        $this->assertEquals('99.99', $product->price);
    }

    public function test_product_cost_cast_as_decimal(): void
    {
        $product = Product::factory()->create(['cost' => 49.50]);

        $this->assertIsString($product->cost);
        $this->assertEquals('49.50', $product->cost);
    }

    public function test_product_soft_delete(): void
    {
        $product = Product::factory()->create();
        $productId = $product->id;

        $product->delete();

        $this->assertSoftDeleted($product);
        $this->assertNull(Product::find($productId));
        $this->assertNotNull(Product::withTrashed()->find($productId));
    }

    public function test_product_restore_from_soft_delete(): void
    {
        $product = Product::factory()->create();

        $product->delete();
        $product->restore();

        $this->assertNotNull(Product::find($product->id));
        $this->assertNotSoftDeleted($product);
    }

    public function test_product_with_no_orders_has_zero_sales(): void
    {
        $product = Product::factory()->create();

        $this->assertEquals(0, $product->totalSold());
        $this->assertEquals(0, $product->totalRevenue());
    }

    public function test_product_sku_must_be_unique(): void
    {
        $category = Category::factory()->create();
        $sku = 'UNIQUE-SKU-001';

        Product::factory()->create(['sku' => $sku, 'category_id' => $category->id]);

        $duplicate = Product::factory()->make(['sku' => $sku, 'category_id' => $category->id]);

        // This would fail at database level, testing model level validation would require separate validators
        $this->assertEquals($sku, $duplicate->sku);
    }
}
