<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_duplicate_email_returns_422(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create(['slug' => 'pro']);

        $this->post('/admin/api/tenants', [
            'name' => 'First Tenant',
            'email' => 'dupe@example.com',
            'domain' => 'first.example.com',
            'plan' => 'pro',
        ])->assertStatus(201);

        $response = $this->post('/admin/api/tenants', [
            'name' => 'Second Tenant',
            'email' => 'dupe@example.com',
            'domain' => 'second.example.com',
            'plan' => 'pro',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email']]);

        $this->assertStringContainsString(
            'already exists',
            $response->json('errors.email')[0] ?? '',
        );
    }

    public function test_duplicate_domain_returns_422(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create(['slug' => 'pro']);

        $this->post('/admin/api/tenants', [
            'name' => 'First Tenant',
            'email' => 'first@example.com',
            'domain' => 'shared.example.com',
            'plan' => 'pro',
        ])->assertStatus(201);

        $response = $this->post('/admin/api/tenants', [
            'name' => 'Second Tenant',
            'email' => 'second@example.com',
            'domain' => 'shared.example.com',
            'plan' => 'pro',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['domain']]);

        $this->assertStringContainsString(
            'already in use',
            $response->json('errors.domain')[0] ?? '',
        );
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

    public function test_change_plan_to_paid_plan_derives_ends_at_from_plan_duration(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create(['duration_months' => null]);
        $tenant = Tenant::factory()->create(['status' => 'Active', 'plan_id' => $plan->id]);
        Subscription::factory()->for($tenant)->for($plan)->create(['status' => 'active']);
        $paidPlan = Plan::factory()->create(['duration_months' => 3]);

        $response = $this->put("/admin/api/tenants/{$tenant->id}/plan", [
            'plan_id' => $paidPlan->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Tenant plan changed successfully');

        $tenant->refresh();
        $this->assertEquals($paidPlan->id, $tenant->plan_id);

        $active = $tenant->activeSubscription;
        $this->assertNotNull($active);
        $this->assertEquals('active', $active->status);
        $this->assertEquals(
            now()->addMonths(3)->format('Y-m-d H:i'),
            $active->ends_at->format('Y-m-d H:i')
        );
    }

    public function test_update_name_email_and_plan_simultaneously(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['status' => 'Active', 'plan_id' => $plan->id]);
        Subscription::factory()->for($tenant)->for($plan)->create(['status' => 'active']);
        $newPlan = Plan::factory()->create();

        $response = $this->put("/admin/api/tenants/{$tenant->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'plan_id' => $newPlan->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Tenant updated successfully');

        $tenant->refresh();
        $this->assertEquals('Updated Name', $tenant->name);
        $this->assertEquals('updated@example.com', $tenant->email);
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

    public function test_change_plan_to_trial_sets_trial_status_and_end_date(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['status' => 'Active', 'plan_id' => $plan->id]);
        Subscription::factory()->for($tenant)->for($plan)->create(['status' => 'active']);
        $trialPlan = Plan::factory()->create(['slug' => 'trial', 'price' => 0]);

        $response = $this->put("/admin/api/tenants/{$tenant->id}/plan", [
            'plan_id' => $trialPlan->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Tenant plan changed successfully');

        $tenant->refresh();
        $this->assertEquals('Trial', $tenant->status);
        $this->assertNotNull($tenant->trial_ends_at);
        $this->assertTrue($tenant->trial_ends_at->isFuture());
        $this->assertEqualsWithDelta(14, now()->diffInDays($tenant->trial_ends_at, false), 1);
    }

    public function test_change_plan_away_from_trial_activates_tenant(): void
    {
        $this->setUpAdminAuth();

        $trialPlan = Plan::factory()->create(['slug' => 'trial', 'price' => 0]);
        $tenant = Tenant::factory()->create([
            'status' => 'Trial',
            'plan_id' => $trialPlan->id,
            'trial_ends_at' => now()->addDays(5),
        ]);
        Subscription::factory()->for($tenant)->for($trialPlan)->create(['status' => 'active']);
        $paidPlan = Plan::factory()->create();

        $response = $this->put("/admin/api/tenants/{$tenant->id}/plan", [
            'plan_id' => $paidPlan->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Tenant plan changed successfully');

        $tenant->refresh();
        $this->assertEquals('Active', $tenant->status);
        $this->assertNull($tenant->trial_ends_at);
    }

    public function test_bulk_change_plan_to_trial_marks_tenants_as_trial(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create();
        $tenants = Tenant::factory(2)->create(['status' => 'Active', 'plan_id' => $plan->id]);
        foreach ($tenants as $tenant) {
            Subscription::factory()->for($tenant)->for($plan)->create(['status' => 'active']);
        }
        $trialPlan = Plan::factory()->create(['slug' => 'trial', 'price' => 0]);

        $this->post('/admin/api/tenants/bulk', [
            'tenant_ids' => $tenants->pluck('id')->toArray(),
            'action' => 'change_plan',
            'payload' => ['plan_id' => $trialPlan->id],
        ])->assertStatus(200)
            ->assertJsonPath('succeeded', 2);

        $tenants->each(function (Tenant $tenant): void {
            $tenant->refresh();
            $this->assertEquals('Trial', $tenant->status);
            $this->assertNotNull($tenant->trial_ends_at);
        });
    }

    public function test_creating_tenant_with_trial_plan_enters_trial(): void
    {
        $this->setUpAdminAuth();

        Plan::factory()->create(['slug' => 'trial', 'price' => 0]);

        $response = $this->post('/admin/api/tenants', [
            'name' => 'Trial Tenant',
            'email' => 'trial@example.com',
            'domain' => 'trial.example.com',
            'plan' => 'trial',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('tenant.status', 'Trial');

        $tenant = Tenant::where('email', 'trial@example.com')->first();
        $this->assertNotNull($tenant->trial_ends_at);
        $this->assertTrue($tenant->trial_ends_at->isFuture());
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

    public function test_tenant_list_includes_subscription_ends_at(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create(['duration_months' => 3]);
        $startsAt = now();

        $withSubscription = Tenant::factory()->create(['status' => 'Active', 'plan_id' => $plan->id]);
        Subscription::createForTenant($withSubscription, $plan, 'active', null, $startsAt);

        $withoutSubscription = Tenant::factory()->create(['status' => 'Active', 'plan_id' => $plan->id]);

        $response = $this->get('/admin/api/tenants');

        $response->assertStatus(200);

        $tenants = collect($response->json('tenants'))->keyBy('id');

        $this->assertTrue($tenants[$withSubscription->id]['has_active_subscription']);
        $this->assertNotNull($tenants[$withSubscription->id]['subscription_ends_at']);
        $this->assertEquals(
            $startsAt->addMonths(3)->format('Y-m-d H:i'),
            Carbon::parse($tenants[$withSubscription->id]['subscription_ends_at'])->setTimezone('UTC')->format('Y-m-d H:i')
        );
        $this->assertEquals($plan->name, $tenants[$withSubscription->id]['subscription_plan_name']);
        $this->assertEquals(3, $tenants[$withSubscription->id]['subscription_duration_months']);

        $this->assertNull($tenants[$withoutSubscription->id]['has_active_subscription']);
        $this->assertNull($tenants[$withoutSubscription->id]['subscription_ends_at']);
        $this->assertNull($tenants[$withoutSubscription->id]['subscription_plan_name']);
    }

    public function test_tenant_list_subscription_ends_at_tracks_plan_duration_changes(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create(['duration_months' => 3]);
        $startsAt = now();

        $tenant = Tenant::factory()->create(['status' => 'Active', 'plan_id' => $plan->id]);
        Subscription::createForTenant($tenant, $plan, 'active', null, $startsAt);

        $plan->update(['duration_months' => 6]);

        $response = $this->get('/admin/api/tenants');

        $response->assertStatus(200);

        $tenants = collect($response->json('tenants'))->keyBy('id');

        $this->assertEquals(
            $startsAt->addMonths(6)->format('Y-m-d H:i'),
            Carbon::parse($tenants[$tenant->id]['subscription_ends_at'])->setTimezone('UTC')->format('Y-m-d H:i')
        );
        $this->assertEquals(6, $tenants[$tenant->id]['subscription_duration_months']);
    }

    public function test_tenant_list_subscription_ends_at_falls_back_to_stored_value_when_plan_has_no_duration(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create(['duration_months' => null]);
        $endsAt = now()->addDays(20);

        $tenant = Tenant::factory()->create(['status' => 'Active', 'plan_id' => $plan->id]);
        Subscription::createForTenant($tenant, $plan, 'active', $endsAt);

        $response = $this->get('/admin/api/tenants');

        $response->assertStatus(200);

        $tenants = collect($response->json('tenants'))->keyBy('id');

        $this->assertNull($tenants[$tenant->id]['subscription_duration_months']);
        $this->assertEquals(
            $endsAt->setTimezone('UTC')->format('Y-m-d H:i'),
            Carbon::parse($tenants[$tenant->id]['subscription_ends_at'])->setTimezone('UTC')->format('Y-m-d H:i')
        );
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

    public function test_plans_list_includes_trial_plan(): void
    {
        $this->setUpAdminAuth();

        $this->seed(PlanSeeder::class);

        $response = $this->get('/admin/api/plans-list');

        $response->assertStatus(200);
        $slugs = collect($response->json('plans'))->pluck('slug')->all();

        $this->assertContains('trial', $slugs);
        $this->assertContains('free', $slugs);
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
        $permission = Permission::firstOrCreate(['name' => 'delete tenants', 'guard_name' => 'admin']);
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
        $permission = Permission::firstOrCreate(['name' => 'delete tenants', 'guard_name' => 'admin']);
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
