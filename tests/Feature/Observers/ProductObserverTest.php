<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantResourceUsage;
use App\Observers\Tenant\ProductObserver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductObserverTest extends TestCase
{
    protected Tenant $tenant;

    /** @var array<int, Tenant> */
    protected array $createdTenants = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        $this->createdTenants[] = $this->tenant;
        $this->initializeTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        $this->forgetTenant();

        // Manual cleanup: delete usage records and tenants, then drop databases.
        // We cannot rely on DatabaseTransactions because CREATE/DROP DATABASE are
        // DDL statements that auto-commit MySQL transactions.
        $tenantIds = [];
        foreach ($this->createdTenants as $t) {
            $tenantIds[] = $t->id;
            try {
                $dbName = $t->database()->getName();
                DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
            } catch (\Exception $e) {
                // Silently ignore — the DB may already be gone or inaccessible
            }
        }

        if ($tenantIds !== []) {
            TenantResourceUsage::whereIn('tenant_id', $tenantIds)->delete();
            Tenant::whereIn('id', $tenantIds)->delete();
        }

        parent::tearDown();
    }

    // -------------------------------------------------------------------
    //  Created event
    // -------------------------------------------------------------------

    public function test_creating_product_increments_products_count(): void
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

    // -------------------------------------------------------------------
    //  Deleted event (soft delete)
    // -------------------------------------------------------------------

    public function test_soft_deleting_product_decrements_products_count(): void
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

    // -------------------------------------------------------------------
    //  Restored event
    // -------------------------------------------------------------------

    public function test_restoring_soft_deleted_product_increments_products_count(): void
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

    public function test_restoring_multiple_products(): void
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

    // -------------------------------------------------------------------
    //  No-op in central context
    // -------------------------------------------------------------------

    public function test_created_does_nothing_outside_tenant_context(): void
    {
        $this->forgetTenant();
        $this->assertNull(tenant('id'));

        $product = Product::factory()->make(['id' => 999, 'category_id' => null]);

        $observer = new ProductObserver;
        $observer->created($product);

        $this->assertEquals(0, TenantResourceUsage::count());
    }

    public function test_deleted_does_nothing_outside_tenant_context(): void
    {
        $this->forgetTenant();
        $this->assertNull(tenant('id'));

        $product = Product::factory()->make(['id' => 999, 'category_id' => null]);

        $observer = new ProductObserver;
        $observer->deleted($product);

        $this->assertEquals(0, TenantResourceUsage::count());
    }

    public function test_restored_does_nothing_outside_tenant_context(): void
    {
        $this->forgetTenant();
        $this->assertNull(tenant('id'));

        $product = Product::factory()->make(['id' => 999, 'category_id' => null]);

        $observer = new ProductObserver;
        $observer->restored($product);

        $this->assertEquals(0, TenantResourceUsage::count());
    }

    // -------------------------------------------------------------------
    //  Tenant isolation
    // -------------------------------------------------------------------

    public function test_creating_product_in_tenant_a_does_not_affect_tenant_b(): void
    {
        $tenantA = $this->tenant;

        Product::factory()->create();

        $this->forgetTenant();

        $tenantB = $this->createTestTenant();
        $this->createdTenants[] = $tenantB;
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
        Product::factory()->create();
        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count')
        );

        $this->forgetTenant();

        $tenantB = $this->createTestTenant();
        $this->createdTenants[] = $tenantB;
        $this->initializeTenant($tenantB);

        $productB = Product::factory()->create();
        $productB->delete();

        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('products_count'),
            'Tenant A product count should be unchanged'
        );
    }

    // -------------------------------------------------------------------
    //  Rapid successive operations
    // -------------------------------------------------------------------

    public function test_rapid_successive_creates_deletes_restores_produce_correct_count(): void
    {
        $p1 = Product::factory()->create();
        $p2 = Product::factory()->create();
        $p1->delete();
        $p3 = Product::factory()->create();
        $p2->delete();

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
