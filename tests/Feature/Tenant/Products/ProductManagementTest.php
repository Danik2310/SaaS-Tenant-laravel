<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Products;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\TenantUserRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Feature coverage for the tenant product surface: authentication, permission
 * enforcement (products/categories/warehouses share the pattern), pagination
 * and write validation. Previously none of these routes had feature tests.
 */
class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTestTenant();

        // Give the tenant an unlimited plan so storage/product gates never
        // interfere with the assertions below.
        $trial = Plan::where('slug', 'trial')->firstOrFail();
        $this->tenant->plan_id = $trial->id;
        $this->tenant->saveQuietly();

        $this->initializeTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        $this->forgetTenant();
        if (isset($this->tenant)) {
            DB::statement("DROP DATABASE IF EXISTS `{$this->tenant->database()->getName()}`");
        }
        parent::tearDown();
    }

    private function userWithRole(string $role): User
    {
        $this->seed(TenantUserRolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/products')->assertRedirect(route('login'));
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/products')->assertForbidden();
    }

    public function test_restricted_role_is_forbidden_from_products_and_inventory(): void
    {
        $cashier = $this->userWithRole('cashier');

        $this->actingAs($cashier)->get('/products')->assertForbidden();
        $this->actingAs($cashier)->get('/categories')->assertForbidden();
        $this->actingAs($cashier)->get('/warehouses')->assertForbidden();
    }

    public function test_manager_can_view_paginated_products(): void
    {
        $manager = $this->userWithRole('manager');
        Product::factory()->count(7)->create();

        $this->actingAs($manager)
            ->get('/products')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Products/Index', false)
                ->has('products.data', 5)
                ->where('products.total', 7));
    }

    public function test_store_rejects_missing_required_fields(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->from('/products/create')
            ->post('/products', [])
            ->assertSessionHasErrors(['name', 'sku', 'price']);
    }

    public function test_manager_can_create_a_product(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->post('/products', [
                'name' => 'Test Widget',
                'sku' => 'SKU-TEST-01',
                'price' => 19.99,
                'active' => true,
            ])
            ->assertRedirect(route('tenant.products.index'));

        $this->assertDatabaseHas('products', [
            'sku' => 'SKU-TEST-01',
            'name' => 'Test Widget',
        ]);
    }
}
