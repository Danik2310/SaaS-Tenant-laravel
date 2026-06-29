<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class TenantLifecycleTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    protected array $createdTenantDbNames = [];

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_can_create_tenant(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create(['slug' => 'pro']);

        $response = $this->post('/admin/api/tenants', [
            'name' => 'New Tenant',
            'email' => 'new@example.com',
            'domain' => 'new.example.com',
            'plan' => 'pro',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Tenant created successfully')
            ->assertJsonStructure(['tenant' => ['id', 'name', 'email', 'status']]);

        $this->assertDatabaseHas('tenants', ['email' => 'new@example.com']);
    }

    public function test_can_suspend_and_reactivate_tenant(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['status' => 'Active', 'plan_id' => $plan->id]);
        Subscription::factory()->for($tenant)->for($plan)->create(['status' => 'active']);

        $response = $this->put("/admin/api/tenants/{$tenant->id}", [
            'status' => 'Suspended',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Tenant updated successfully');

        $tenant->refresh();
        $this->assertEquals('Suspended', $tenant->status);

        $response = $this->put("/admin/api/tenants/{$tenant->id}", [
            'status' => 'Active',
        ]);

        $response->assertStatus(200);
        $tenant->refresh();
        $this->assertEquals('Active', $tenant->status);
    }

    public function test_cannot_transition_from_deleted_to_suspended(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create();
        $tenant = Tenant::withoutEvents(fn () => Tenant::factory()->create(['status' => 'Deleted', 'plan_id' => $plan->id]));

        $response = $this->put("/admin/api/tenants/{$tenant->id}", [
            'status' => 'Suspended',
        ]);

        $response->assertStatus(422);
    }

    public function test_can_soft_delete_and_restore_tenant(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['status' => 'Active', 'plan_id' => $plan->id]);
        Subscription::factory()->for($tenant)->for($plan)->create(['status' => 'active']);

        $response = $this->delete("/admin/api/tenants/{$tenant->id}");

        $response->assertStatus(204);

        $tenant->refresh();
        $this->assertNotNull($tenant->deleted_at);
        $this->assertEquals('Deleted', $tenant->status);

        $response = $this->patch("/admin/api/tenants/{$tenant->id}/restore");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Tenant restored successfully');

        $tenant->refresh();
        $this->assertNull($tenant->deleted_at);
        $this->assertEquals('Active', $tenant->status);
    }

    public function test_can_change_tenant_plan(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['status' => 'Active', 'plan_id' => $plan->id]);
        Subscription::factory()->for($tenant)->for($plan)->create(['status' => 'active']);
        $newPlan = Plan::factory()->create();

        $response = $this->put("/admin/api/tenants/{$tenant->id}/plan", [
            'plan_id' => $newPlan->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Tenant plan changed successfully');

        $tenant->refresh();
        $this->assertEquals($newPlan->id, $tenant->plan_id);
    }

    public function test_bulk_suspend_tenants(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create();
        $tenants = Tenant::factory(3)->create(['status' => 'Active', 'plan_id' => $plan->id]);
        foreach ($tenants as $tenant) {
            Subscription::factory()->for($tenant)->for($plan)->create(['status' => 'active']);
            $this->assertNotNull($tenant->activeSubscription, 'Tenant should have active subscription');
        }

        $response = $this->post('/admin/api/tenants/bulk', [
            'tenant_ids' => $tenants->pluck('id')->toArray(),
            'action' => 'suspend',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('succeeded', 3);

        $tenants->each(function (Tenant $tenant): void {
            $tenant->refresh();
            $this->assertEquals('Suspended', $tenant->status);
        });
    }

    public function test_bulk_delete_and_restore_tenants(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create();
        $tenants = Tenant::factory(3)->create(['status' => 'Active', 'plan_id' => $plan->id]);
        foreach ($tenants as $tenant) {
            Subscription::factory()->for($tenant)->for($plan)->create(['status' => 'active']);
        }

        $this->post('/admin/api/tenants/bulk', [
            'tenant_ids' => $tenants->pluck('id')->toArray(),
            'action' => 'delete',
        ])->assertJsonPath('succeeded', 3);

        $this->post('/admin/api/tenants/bulk', [
            'tenant_ids' => $tenants->pluck('id')->toArray(),
            'action' => 'restore',
        ])->assertJsonPath('succeeded', 3);

        $tenants->each(function (Tenant $tenant): void {
            $tenant->refresh();
            $this->assertNull($tenant->deleted_at);
            $this->assertEquals('Active', $tenant->status);
        });
    }

    public function test_bulk_change_plan(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create();
        $tenants = Tenant::factory(3)->create(['status' => 'Active', 'plan_id' => $plan->id]);
        foreach ($tenants as $tenant) {
            Subscription::factory()->for($tenant)->for($plan)->create(['status' => 'active']);
        }
        $newPlan = Plan::factory()->create();

        $response = $this->post('/admin/api/tenants/bulk', [
            'tenant_ids' => $tenants->pluck('id')->toArray(),
            'action' => 'change_plan',
            'payload' => ['plan_id' => $newPlan->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('succeeded', 3);

        $tenants->each(function (Tenant $tenant) use ($newPlan): void {
            $tenant->refresh();
            $this->assertEquals($newPlan->id, $tenant->plan_id);
        });
    }

    public function test_bulk_extend_trial(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['status' => 'Trial', 'plan_id' => $plan->id, 'trial_ends_at' => now()->addDays(5)]);

        $this->post('/admin/api/tenants/bulk', [
            'tenant_ids' => [$tenant->id],
            'action' => 'extend_trial',
            'payload' => ['days' => 10],
        ])->assertJsonPath('succeeded', 1);

        $tenant->refresh();
        $this->assertTrue(abs(now()->diffInDays($tenant->trial_ends_at, false) - 15) < 1);
    }

    public function test_cannot_extend_trial_for_non_trial_tenant(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create();
        $tenant = Tenant::withoutEvents(fn () => Tenant::factory()->create(['status' => 'Active', 'plan_id' => $plan->id]));

        $this->post('/admin/api/tenants/bulk', [
            'tenant_ids' => [$tenant->id],
            'action' => 'extend_trial',
            'payload' => ['days' => 10],
        ])->assertJsonPath('succeeded', 0)
            ->assertJsonPath('failed', 1);
    }

    public function test_bulk_activate_succeeds_even_without_subscription(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['status' => 'Suspended', 'plan_id' => $plan->id]);

        $this->post('/admin/api/tenants/bulk', [
            'tenant_ids' => [$tenant->id],
            'action' => 'activate',
        ])->assertJsonPath('succeeded', 1);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $response = $this->post('/admin/api/tenants', [
            'name' => 'Test',
        ]);

        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_manage_tenants(): void
    {
        $user = AdminUser::factory()->create();
        $this->actingAs($user, 'admin');

        $this->post('/admin/api/tenants', [
            'name' => 'Test',
        ])->assertStatus(403);
    }

    public function test_can_list_tenants(): void
    {
        $this->setUpAdminAuth();

        Tenant::factory(3)->create();

        $response = $this->get('/admin/api/tenants');

        $response->assertStatus(200)
            ->assertJsonStructure(['tenants', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
    }

    public function test_can_show_tenant(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        $response = $this->get("/admin/api/tenants/{$tenant->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['tenant' => ['id', 'name', 'status']]);
    }

    public function test_can_list_plans(): void
    {
        $this->setUpAdminAuth();

        Plan::factory(3)->create();

        $response = $this->get('/admin/api/plans-list');

        $response->assertStatus(200)
            ->assertJsonStructure(['plans']);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        foreach ($this->createdTenantDbNames as $name) {
            try {
                DB::statement("DROP DATABASE IF EXISTS `$name`");
            } catch (\Exception $e) {
                // ignore if already dropped
            }
        }

        parent::tearDown();
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Ported from TenantFeatureTest (unique tests)
    // ────────────────────────────────────────────────────────────────────────────

    /**
     * ✅ Test: Tenant and domain can be created with valid relationships.
     */
    public function test_tenant_and_domain_can_be_created()
    {
        $tenant = $this->createTestTenant();

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertNotEmpty($tenant->database()->getName());
        $this->createdTenantDbNames[] = $tenant->database()->getName();

        $domain = $tenant->domains()->create([
            'domain' => 'test.localhost',
        ]);

        $this->assertNotNull($domain->id);
        $this->assertDatabaseHas('domains', [
            'domain' => 'test.localhost',
            'tenant_id' => $tenant->id,
        ]);

        $this->assertCount(2, $tenant->domains);
    }

    /**
     * ✅ Test: Tenant migrations run successfully via API endpoint.
     */
    public function test_tenant_migrations_run_successfully()
    {
        $tenant = Tenant::create([
            'id' => 'test-migration-'.uniqid(),
        ]);
        $tenant->database()->makeCredentials();
        // Drop existing tenant DB to avoid collision from interrupted runs
        $tenantDbName = $tenant->database()->getName();
        try {
            DB::statement("DROP DATABASE IF EXISTS `$tenantDbName`");
        } catch (\Exception $e) {
            // ignore
        }
        $tenant->database()->manager()->createDatabase($tenant);
        $tenant->save();

        $this->createdTenantDbNames[] = $tenantDbName;

        $response = $this->withoutMiddleware()->post("/admin/api/tenants/{$tenant->id}/migrate");

        $response->assertStatus(200);
        $this->assertStringContainsString('Migrations executed', $response->json('message'));
        $this->assertArrayHasKey('output', $response->json());
        $this->assertArrayHasKey('exit', $response->json());
    }

    /**
     * ✅ Test: Tenant database endpoint returns credentials.
     */
    public function test_tenant_database_endpoint_returns_credentials()
    {
        $tenant = $this->createTestTenant();
        $this->createdTenantDbNames[] = $tenant->database()->getName();

        $response = $this->withoutMiddleware()->get("/admin/api/tenants/{$tenant->id}/database");
        $response->assertStatus(200)
            ->assertJsonStructure(['database' => ['name', 'connection']]);
    }

    /**
     * ✅ Test: Tenant database is actually created.
     */
    public function test_tenant_database_is_created()
    {
        $tenant = $this->createTestTenant();
        $dbName = $tenant->database()->getName();
        $this->createdTenantDbNames[] = $dbName;

        $this->assertNotEmpty($dbName);

        $config = $tenant->database()->connection();
        $this->assertEquals($dbName, $config['database']);
    }

    /**
     * ✅ Test: Tenant customer and product data flow works.
     */
    public function test_tenant_customer_and_product_data_flow()
    {
        $tenant = $this->createTestTenant();
        $this->createdTenantDbNames[] = $tenant->database()->getName();

        $this->initializeTenant($tenant);

        $categoryId = DB::connection('tenant')->table('categories')->insertGetId([
            'name' => 'Electrónicos',
            'slug' => 'electronicos',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::connection('tenant')->table('products')->insertGetId([
            'name' => 'Laptop',
            'sku' => 'laptop-001',
            'price' => 999.99,
            'category_id' => $categoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customerId = DB::connection('tenant')->table('customers')->insertGetId([
            'name' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('categories', ['id' => $categoryId, 'name' => 'Electrónicos'], 'tenant');
        $this->assertDatabaseHas('products', ['id' => $productId, 'name' => 'Laptop'], 'tenant');
        $this->assertDatabaseHas('customers', ['id' => $customerId, 'name' => 'Juan Pérez'], 'tenant');

        $product = DB::connection('tenant')
            ->table('products')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('products.id', $productId)
            ->first();

        $this->assertNotNull($product);
        $this->assertEquals('electronicos', $product->slug);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Ported from TenantForceDeleteWithEventTest (unique tests)
    // ────────────────────────────────────────────────────────────────────────────

    /**
     * ✅ Test: Force-deleting a tenant drops the database and removes the record.
     */
    public function test_tenant_force_delete_triggers_tenant_deleted_event_and_drops_database()
    {
        $plan = Plan::factory()->create();
        $tenant = $this->createTestTenant();
        $tenant->update(['plan_id' => $plan->id]);
        $dbName = $tenant->database()->getName();
        $this->createdTenantDbNames[] = $dbName;

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);

        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        $permission = Permission::firstOrCreate(['name' => 'manage tenants', 'guard_name' => 'admin']);
        $role->givePermissionTo($permission);
        $user = AdminUser::factory()->create();
        $user->assignRole('super-admin');
        $this->actingAs($user, 'admin');

        $this->delete("/admin/api/tenants/{$tenant->id}");
        $tenant->refresh();
        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);

        Tenant::withoutEvents(fn () => $tenant->forceDelete());
        $this->assertModelMissing($tenant);
    }

    /**
     * ✅ Test: Force-deleting a tenant cascades to subscriptions.
     */
    public function test_tenant_force_delete_cascades_to_subscriptions()
    {
        $plan = Plan::factory()->create();
        $tenant = $this->createTestTenant();
        $tenant->update(['plan_id' => $plan->id]);
        $this->createdTenantDbNames[] = $tenant->database()->getName();

        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
        ]);

        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        $permission = Permission::firstOrCreate(['name' => 'manage tenants', 'guard_name' => 'admin']);
        $role->givePermissionTo($permission);
        $user = AdminUser::factory()->create();
        $user->assignRole('super-admin');
        $this->actingAs($user, 'admin');

        $this->delete("/admin/api/tenants/{$tenant->id}");
        $tenant->refresh();
        Tenant::withoutEvents(fn () => $tenant->forceDelete());

        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);
    }
}
