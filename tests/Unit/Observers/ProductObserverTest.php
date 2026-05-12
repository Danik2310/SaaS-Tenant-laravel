<?php

namespace Tests\Unit\Observers;

use App\Models\Product;
use App\Models\TenantResourceUsage;
use App\Observers\Tenant\ProductObserver;

class ProductObserverTest extends ObserverTestCase
{
    // -----------------------------------------------------------------------
    //  Created event
    // -----------------------------------------------------------------------

    public function test_product_created_increments_products_count(): void
    {
        Product::factory()->create();

        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count')
        );
    }

    public function test_multiple_products_created_produce_correct_count(): void
    {
        Product::factory()->count(4)->create();

        $this->assertEquals(
            4,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count')
        );
    }

    // -----------------------------------------------------------------------
    //  Deleted event (soft delete)
    // -----------------------------------------------------------------------

    public function test_product_soft_deleted_decrements_products_count(): void
    {
        $product = Product::factory()->create();
        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count')
        );

        $product->delete();

        $this->assertEquals(
            0,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count')
        );
    }

    public function test_multiple_products_soft_deleted_adjusts_count(): void
    {
        $products = Product::factory()->count(3)->create();
        $this->assertEquals(
            3,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count')
        );

        $products[0]->delete();
        $this->assertEquals(
            2,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count')
        );

        $products[1]->delete();
        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count')
        );

        $products[2]->delete();
        $this->assertEquals(
            0,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count')
        );
    }

    // -----------------------------------------------------------------------
    //  Restored event
    // -----------------------------------------------------------------------

    public function test_product_restored_increments_products_count_after_soft_delete(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $this->assertEquals(
            0,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count'),
            'Count should be 0 after soft delete'
        );

        $product->restore();

        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count'),
            'Count should return to 1 after restore'
        );
    }

    public function test_product_restore_fires_on_non_deleted_model_and_still_counts(): void
    {
        // Eloquent fires the restored event even when the model was not soft-deleted.
        // The observer is a simple counter — it cannot distinguish "restored from trash"
        // from "restore called but wasn't deleted". This test documents that behavior.
        $product = Product::factory()->create();
        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count')
        );

        $product->restore();

        // The restored event fires, so the observer increments again
        $this->assertEquals(
            2,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count')
        );
    }

    public function test_restore_multiple_products(): void
    {
        $products = Product::factory()->count(3)->create();
        foreach ($products as $p) {
            $p->delete();
        }

        $this->assertEquals(
            0,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count')
        );

        $products[0]->restore();
        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count')
        );

        $products[1]->restore();
        $this->assertEquals(
            2,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count')
        );
    }

    // -----------------------------------------------------------------------
    //  No-op in central context
    // -----------------------------------------------------------------------

    public function test_created_does_nothing_when_tenant_id_is_null(): void
    {
        $this->forgetTenant();
        $this->assertNull(tenant('id'));

        // Override category_id to null to avoid the factory trying to create a
        // Category in the central DB (where the categories table doesn't exist).
        $product = Product::factory()->make(['id' => 999, 'category_id' => null]);

        $observer = new ProductObserver;
        $observer->created($product);

        $this->assertEquals(0, TenantResourceUsage::count());
    }

    public function test_deleted_does_nothing_when_tenant_id_is_null(): void
    {
        $this->forgetTenant();
        $this->assertNull(tenant('id'));

        $product = Product::factory()->make(['id' => 999, 'category_id' => null]);

        $observer = new ProductObserver;
        $observer->deleted($product);

        $this->assertEquals(0, TenantResourceUsage::count());
    }

    public function test_restored_does_nothing_when_tenant_id_is_null(): void
    {
        $this->forgetTenant();
        $this->assertNull(tenant('id'));

        $product = Product::factory()->make(['id' => 999, 'category_id' => null]);

        $observer = new ProductObserver;
        $observer->restored($product);

        $this->assertEquals(0, TenantResourceUsage::count());
    }

    // -----------------------------------------------------------------------
    //  Tenant isolation
    // -----------------------------------------------------------------------

    public function test_creating_product_in_tenant_a_does_not_affect_tenant_b(): void
    {
        $tenantA = $this->tenant;

        Product::factory()->create();

        $this->forgetTenant();

        $tenantB = $this->createTestTenant();
        $this->createdDatabases[] = $tenantB->database()->getName();
        $this->initializeTenant($tenantB);

        Product::factory()->count(2)->create();

        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $tenantA->id)->value('products_count'),
            'Tenant A should have 1 product'
        );
        $this->assertEquals(
            2,
            TenantResourceUsage::where('tenant_id', $tenantB->id)->value('products_count'),
            'Tenant B should have 2 products'
        );
    }

    public function test_soft_delete_in_tenant_b_does_not_affect_tenant_a(): void
    {
        $productA = Product::factory()->create();
        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count')
        );

        $this->forgetTenant();

        $tenantB = $this->createTestTenant();
        $this->createdDatabases[] = $tenantB->database()->getName();
        $this->initializeTenant($tenantB);

        $productB = Product::factory()->create();
        $productB->delete();

        // Tenant A unaffected
        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count'),
            'Tenant A product count should be unchanged'
        );
    }

    // -----------------------------------------------------------------------
    //  Observer registration
    // -----------------------------------------------------------------------

    public function test_product_observer_is_registered(): void
    {
        $this->assertTrue(
            Product::getEventDispatcher()->hasListeners('eloquent.created: '.Product::class),
            'Product model should have a listener for the created event'
        );

        Product::factory()->create();

        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count')
        );
    }

    // -----------------------------------------------------------------------
    //  Rapid successive operations
    // -----------------------------------------------------------------------

    public function test_rapid_successive_creates_deletes_restores_produce_correct_count(): void
    {
        $p1 = Product::factory()->create();
        $p2 = Product::factory()->create();
        $p1->delete();
        $p3 = Product::factory()->create();
        $p2->delete();

        // p3 is active, p1 and p2 are soft-deleted
        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count')
        );

        $p1->restore();
        $this->assertEquals(
            2,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count')
        );
    }
}
