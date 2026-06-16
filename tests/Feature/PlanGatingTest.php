<?php

namespace Tests\Feature;

use App\Exceptions\PlanLimitExceededException;
use App\Models\AdminUser;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class PlanGatingTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();
    }

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

    public function test_tenant_has_feature_delegates_to_plan()
    {
        $plan = Plan::factory()->create();
        $plan->featureGates()->create([
            'feature_key' => 'api_access', 'is_enabled' => true,
        ]);

        $tenant = Tenant::create([
            'id' => 'test-tenant-'.uniqid(),
            'name' => 'Test',
            'email' => 'test@example.com',
            'status' => 'Active',
            'plan_id' => $plan->id,
        ]);

        $this->assertTrue($tenant->hasFeature('api_access'));
        $this->assertFalse($tenant->hasFeature('white_label'));
    }

    public function test_tenant_has_feature_returns_false_without_plan()
    {
        $tenant = Tenant::create([
            'id' => 'test-tenant-'.uniqid(),
            'name' => 'Test',
            'email' => 'test@example.com',
            'status' => 'Active',
        ]);

        $this->assertFalse($tenant->hasFeature('api_access'));
    }

    public function test_tenant_get_limit_delegates_to_plan()
    {
        $plan = Plan::factory()->create([
            'max_users' => 5,
        ]);

        $tenant = Tenant::create([
            'id' => 'test-tenant-'.uniqid(),
            'name' => 'Test',
            'email' => 'test@example.com',
            'status' => 'Active',
            'plan_id' => $plan->id,
        ]);

        $this->assertEquals(5, $tenant->getLimit('users'));
    }

    public function test_tenant_get_limit_returns_zero_without_plan()
    {
        $tenant = Tenant::create([
            'id' => 'test-tenant-'.uniqid(),
            'name' => 'Test',
            'email' => 'test@example.com',
            'status' => 'Active',
        ]);

        $this->assertEquals(0, $tenant->getLimit('users'));
    }

    public function test_tenant_is_on_trial()
    {
        $tenant = Tenant::create([
            'id' => 'test-tenant-'.uniqid(),
            'name' => 'Test',
            'status' => 'Trial',
            'trial_ends_at' => now()->addDays(7),
        ]);

        $this->assertTrue($tenant->isOnTrial());
        $this->assertFalse($tenant->trialHasExpired());
    }

    public function test_tenant_trial_has_expired()
    {
        $tenant = Tenant::create([
            'id' => 'test-tenant-'.uniqid(),
            'name' => 'Test',
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

    public function test_can_change_tenant_plan_via_api()
    {
        $plan1 = Plan::factory()->create(['slug' => 'basic', 'max_users' => 5]);
        $plan2 = Plan::factory()->create(['slug' => 'pro', 'max_users' => 50]);

        $tenant = Tenant::create([
            'id' => 'test-tenant-'.uniqid(),
            'name' => 'Test',
            'email' => 'test@example.com',
            'status' => 'Active',
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
        $tenant = Tenant::create([
            'id' => 'test-tenant-'.uniqid(),
            'name' => 'Test',
            'email' => 'test@example.com',
            'status' => 'Active',
        ]);

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

    public function test_store_tenant_request_accepts_plan_slug()
    {
        $plan = Plan::factory()->create(['slug' => 'pro']);

        $response = $this->postJson('/admin/api/tenants', [
            'name' => 'Test Tenant',
            'email' => 'tenant@example.com',
            'domain' => 'test-'.uniqid().'.localhost',
            'plan' => 'pro',
        ]);

        $response->assertStatus(201);
        $tenantId = $response->json('tenant.id');
        $tenant = Tenant::with('plan')->find($tenantId);
        $this->assertNotNull($tenant->plan);
        $this->assertEquals($plan->id, $tenant->plan->id);
    }

    public function test_create_tenant_with_invalid_plan_slug_fails()
    {
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
        $plan = Plan::factory()->create();
        $tenant = Tenant::create([
            'id' => 'test-tenant-'.uniqid(),
            'name' => 'Test',
            'email' => 'test@example.com',
            'status' => 'Active',
            'plan_id' => $plan->id,
        ]);

        $response = $this->getJson("/admin/api/tenants/{$tenant->id}");

        $response->assertStatus(200);
        $this->assertArrayHasKey('plan', $response->json('tenant'));
    }
}
