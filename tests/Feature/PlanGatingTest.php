<?php

namespace Tests\Feature;

use App\Exceptions\PlanLimitExceededException;
use App\Factories\ResourceEnforcementFactory;
use App\Models\AdminUser;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Strategies\EnterpriseResourceStrategy;
use App\Services\Strategies\GrowthResourceStrategy;
use App\Services\Strategies\ProResourceStrategy;
use App\Services\Strategies\StarterResourceStrategy;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class PlanGatingTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    /**
     * No database transactions — cross-connection FK constraints (Tenant on `mysql`, Plan on `mysql_central`)
     * would deadlock or lock-wait if either connection were wrapped in a transaction.
     *
     * migrate:fresh at class start drops all tables (both connections target
     * the same database). Between tests, each test creates records with
     * factory-generated unique slugs/emails to avoid collisions.
     */
    protected $connectionsToTransact = [];

    /**
     * Create a Tenant record in the central DB without triggering tenancy
     * bootstrappers that would CREATE DATABASE (DDL — implicit MySQL commit).
     *
     * Use this for model-level assertions that only need the Tenant eloquent
     * model with its attributes, not the actual tenant database/migrations.
     */
    private function createTenantWithoutEvents(array $attributes = []): Tenant
    {
        return Tenant::withoutEvents(function () use ($attributes) {
            return Tenant::factory()->create($attributes);
        });
    }

    // =========================================================================
    // Plan model tests — no Tenant records needed
    // =========================================================================

    public function test_plan_has_feature_returns_true_for_existing_feature()
    {
        $plan = Plan::factory()->create();
        $plan->featureGates()->createMany([
            ['feature_key' => 'api_access', 'is_enabled' => true],
            ['feature_key' => 'custom_domain', 'is_enabled' => true],
        ]);

        $this->assertTrue($plan->hasFeature('api_access'));
        $this->assertTrue($plan->hasFeature('custom_domain'));
    }

    public function test_plan_has_feature_returns_false_for_missing_feature()
    {
        $plan = Plan::factory()->create();
        $plan->featureGates()->create([
            'feature_key' => 'api_access', 'is_enabled' => true,
        ]);

        $this->assertFalse($plan->hasFeature('white_label'));
    }

    public function test_plan_get_limit_returns_users_limit()
    {
        $plan = Plan::factory()->create([
            'max_users' => 10,
        ]);

        $this->assertEquals(10, $plan->getLimit('users'));
    }

    public function test_plan_get_limit_returns_max_int_when_not_set_unlimited()
    {
        $plan = Plan::factory()->create([
            'max_users' => null,
        ]);

        $this->assertEquals(PHP_INT_MAX, $plan->getLimit('users'));
    }

    // =========================================================================
    // Tenant model behavior tests — need Tenant records but NOT tenant database
    // =========================================================================

    public function test_tenant_has_feature_delegates_to_plan()
    {
        $plan = Plan::factory()->create();
        $plan->featureGates()->create([
            'feature_key' => 'api_access', 'is_enabled' => true,
        ]);

        $tenant = $this->createTenantWithoutEvents([
            'plan_id' => $plan->id,
        ]);

        $this->assertTrue($tenant->hasFeature('api_access'));
        $this->assertFalse($tenant->hasFeature('white_label'));
    }

    public function test_tenant_has_feature_returns_false_without_plan()
    {
        $tenant = $this->createTenantWithoutEvents();

        $this->assertFalse($tenant->hasFeature('api_access'));
    }

    public function test_tenant_get_limit_delegates_to_plan()
    {
        $plan = Plan::factory()->create([
            'max_users' => 5,
        ]);

        $tenant = $this->createTenantWithoutEvents([
            'plan_id' => $plan->id,
        ]);

        $this->assertEquals(5, $tenant->getLimit('users'));
    }

    public function test_tenant_get_limit_without_plan_returns_max_int()
    {
        $tenant = $this->createTenantWithoutEvents();

        $this->assertEquals(PHP_INT_MAX, $tenant->getLimit('users'));
    }

    public function test_tenant_is_on_trial()
    {
        $tenant = $this->createTenantWithoutEvents([
            'status' => 'Trial',
            'trial_ends_at' => now()->addDays(7),
        ]);

        $this->assertTrue($tenant->isOnTrial());
        $this->assertFalse($tenant->trialHasExpired());
    }

    public function test_tenant_trial_has_expired()
    {
        $tenant = $this->createTenantWithoutEvents([
            'status' => 'Trial',
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->assertFalse($tenant->isOnTrial());
        $this->assertTrue($tenant->trialHasExpired());
    }

    public function test_plan_limit_exceeded_exception_has_correct_message()
    {
        $exception = new PlanLimitExceededException('users', 5);

        $this->assertEquals('You have reached the users limit of 5 on your current plan.', $exception->getMessage());
    }

    public function test_plan_limit_exceeded_exception_is_rendered_as_json()
    {
        $exception = new PlanLimitExceededException('products', 100);
        $request = Request::create('/admin/api/test', 'GET');
        $request->headers->set('Accept', 'application/json');

        $handler = $this->app->make(ExceptionHandler::class);
        $response = $handler->render($request, $exception);
        $data = $response->getData(true);

        $this->assertEquals('You have reached the products limit of 100 on your current plan.', $data['message']);
        $this->assertEquals(403, $response->getStatusCode());
    }

    // =========================================================================
    // API tests — need authentication. setUpAdminAuth() is called inline so
    // the test method that triggers DDL (test_store_tenant_request_accepts_plan_slug)
    // is placed last to avoid corrupting subsequent tests' transactions.
    // =========================================================================

    public function test_can_change_tenant_plan_via_api()
    {
        $this->setUpAdminAuth();

        $suffix = uniqid();
        $plan1 = Plan::factory()->create(['slug' => 'basic-'.$suffix, 'max_users' => 5]);
        $plan2 = Plan::factory()->create(['slug' => 'pro-'.$suffix, 'max_users' => 50]);

        $tenant = $this->createTenantWithoutEvents([
            'plan_id' => $plan1->id,
        ]);

        Subscription::createForTenant($tenant, $plan1, 'active');

        $response = $this->putJson("/admin/api/tenants/{$tenant->id}/plan", [
            'plan_id' => $plan2->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Tenant plan changed successfully');

        $tenant->refresh();
        $this->assertEquals($plan2->id, $tenant->plan_id);

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan1->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan2->id,
            'status' => 'active',
        ]);
    }

    public function test_change_tenant_plan_requires_manage_tenants_permission()
    {
        $plan = Plan::factory()->create();

        $tenant = $this->createTenantWithoutEvents();

        $regularAdmin = AdminUser::factory()->create();
        $regularRole = Role::firstOrCreate([
            'name' => 'regular',
            'guard_name' => 'admin',
        ]);
        $regularAdmin->assignRole('regular');
        $this->actingAs($regularAdmin, 'admin');

        $response = $this->putJson("/admin/api/tenants/{$tenant->id}/plan", [
            'plan_id' => $plan->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_create_tenant_with_invalid_plan_slug_fails()
    {
        $this->setUpAdminAuth();

        $response = $this->postJson('/admin/api/tenants', [
            'name' => 'Test Tenant',
            'email' => 'tenant@example.com',
            'domain' => 'test-'.uniqid().'.localhost',
            'plan' => 'nonexistent-plan',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);
    }

    public function test_plan_resource_includes_plan_in_tenant_response()
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create();

        $tenant = $this->createTenantWithoutEvents([
            'plan_id' => $plan->id,
        ]);

        $response = $this->getJson("/admin/api/tenants/{$tenant->id}");

        $response->assertStatus(200);
        $this->assertArrayHasKey('plan', $response->json('tenant'));
    }

    /**
     * This test goes through the HTTP layer (POST /admin/api/tenants) which
     * invokes TenantBuilder::withData() → Tenant::create() → tenancy
     * bootstrappers → CREATE DATABASE (MySQL DDL).
     *
     * DDL implicitly commits the active transaction, breaking the
     * transaction-based rollback that RefreshDatabase relies on.
     *
     * To minimise impact, this test is placed last in the class so no
     * subsequent test is affected by the committed transaction.
     */
    public function test_store_tenant_request_accepts_plan_slug()
    {
        $this->setUpAdminAuth();

        $suffix = uniqid();
        $slug = 'pro-'.$suffix;
        $plan = Plan::factory()->create(['slug' => $slug]);

        $response = $this->postJson('/admin/api/tenants', [
            'name' => 'Test Tenant',
            'email' => 'tenant@example.com',
            'domain' => 'test-'.$suffix.'.localhost',
            'plan' => $slug,
        ]);

        $response->assertStatus(201);
        $tenantId = $response->json('tenant.id');
        $tenant = Tenant::with('plan')->find($tenantId);
        $this->assertNotNull($tenant->plan);
        $this->assertEquals($plan->id, $tenant->plan->id);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Ported from PlanGatingIntegrationTest (unique tests)
    // ────────────────────────────────────────────────────────────────────────────

    /**
     * 🧪 Test: ResourceEnforcementFactory returns StarterResourceStrategy for free plan
     */
    public function test_resource_enforcement_factory_returns_starter_for_free_plan()
    {
        $plan = Plan::factory()->create(['slug' => 'free', 'max_users' => 5]);
        $tenant = $this->createTenantWithoutEvents(['plan_id' => $plan->id]);

        $strategy = ResourceEnforcementFactory::make($tenant);

        $this->assertInstanceOf(StarterResourceStrategy::class, $strategy);
    }

    /**
     * 🧪 Test: ResourceEnforcementFactory returns GrowthResourceStrategy for growth plan
     */
    public function test_resource_enforcement_factory_returns_growth_for_growth_plan()
    {
        $plan = Plan::factory()->create(['slug' => 'growth', 'max_users' => 20]);
        $tenant = $this->createTenantWithoutEvents(['plan_id' => $plan->id]);

        $strategy = ResourceEnforcementFactory::make($tenant);

        $this->assertInstanceOf(GrowthResourceStrategy::class, $strategy);
    }

    /**
     * 🧪 Test: ResourceEnforcementFactory returns ProResourceStrategy for pro plan
     */
    public function test_resource_enforcement_factory_returns_pro_for_pro_plan()
    {
        $plan = Plan::factory()->create(['slug' => 'pro', 'max_users' => 50]);
        $tenant = $this->createTenantWithoutEvents(['plan_id' => $plan->id]);

        $strategy = ResourceEnforcementFactory::make($tenant);

        $this->assertInstanceOf(ProResourceStrategy::class, $strategy);
    }

    /**
     * 🧪 Test: ResourceEnforcementFactory returns EnterpriseResourceStrategy for enterprise plan
     */
    public function test_resource_enforcement_factory_returns_enterprise_for_enterprise_plan()
    {
        $plan = Plan::factory()->create(['slug' => 'enterprise', 'max_users' => 999]);
        $tenant = $this->createTenantWithoutEvents(['plan_id' => $plan->id]);

        $strategy = ResourceEnforcementFactory::make($tenant);

        $this->assertInstanceOf(EnterpriseResourceStrategy::class, $strategy);
    }

    /**
     * 🧪 Test: Growth resource strategy has correct default limits
     */
    public function test_growth_resource_strategy_has_correct_default_limits()
    {
        $plan = Plan::factory()->create(['slug' => 'growth', 'max_users' => null]);
        foreach (['basic_reports', 'advanced_reports', 'bulk_operations'] as $key) {
            $plan->featureGates()->create(['feature_key' => $key, 'is_enabled' => true]);
        }
        $strategy = new GrowthResourceStrategy($plan);

        $this->assertEquals(PHP_INT_MAX, $strategy->maxUsers());
        $this->assertEquals(PHP_INT_MAX, $strategy->maxStorageMb());
        $this->assertEquals(PHP_INT_MAX, $strategy->maxWarehouses());
        $this->assertEquals(PHP_INT_MAX, $strategy->maxCategories());
        $this->assertEquals(PHP_INT_MAX, $strategy->maxProducts());
        $this->assertEquals(['csv', 'xlsx'], $strategy->allowedExportFormats());
        $this->assertTrue($strategy->hasFeature('basic_reports'));
        $this->assertTrue($strategy->hasFeature('advanced_reports'));
        $this->assertTrue($strategy->hasFeature('bulk_operations'));
    }

    /**
     * 🧪 Test: Growth resource strategy uses plan values when set
     */
    public function test_growth_resource_strategy_uses_plan_values_when_set()
    {
        $plan = Plan::factory()->create([
            'slug' => 'growth',
            'max_users' => 30,
            'max_storage' => 1000,
            'max_warehouses' => 5,
            'max_categories' => 50,
            'max_products' => 500,
        ]);
        $strategy = new GrowthResourceStrategy($plan);

        $this->assertEquals(30, $strategy->maxUsers());
        $this->assertEquals(1000, $strategy->maxStorageMb());
        $this->assertEquals(5, $strategy->maxWarehouses());
        $this->assertEquals(50, $strategy->maxCategories());
        $this->assertEquals(500, $strategy->maxProducts());
    }
}
