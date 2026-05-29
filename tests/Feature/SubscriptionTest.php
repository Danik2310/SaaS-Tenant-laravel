<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();
    }

    public function test_create_for_tenant_creates_active_subscription(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        $subscription = Subscription::createForTenant($tenant, $plan, 'active');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        $this->assertNotNull($subscription->starts_at);
        $this->assertNull($subscription->ends_at);
    }

    public function test_create_for_tenant_sets_ends_at_when_provided(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $endsAt = now()->addDays(30);

        $subscription = Subscription::createForTenant($tenant, $plan, 'active', $endsAt);

        $this->assertEquals($endsAt->format('Y-m-d'), $subscription->ends_at->format('Y-m-d'));
    }

    public function test_tenant_has_subscriptions_relationship(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        Subscription::createForTenant($tenant, $plan, 'active');

        $this->assertCount(1, $tenant->subscriptions);
        $this->assertTrue($tenant->subscriptions->first()->is(Subscription::first()));
    }

    public function test_tenant_active_subscription_scope(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        Subscription::createForTenant($tenant, $plan, 'active');
        Subscription::createForTenant($tenant, $plan, 'cancelled');

        $this->assertNotNull($tenant->activeSubscription);
        $this->assertEquals('active', $tenant->activeSubscription->status);
    }

    public function test_tenant_active_subscription_is_null_when_none_active(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        Subscription::createForTenant($tenant, $plan, 'cancelled');

        $tenant->refresh();
        $this->assertNull($tenant->activeSubscription);
    }

    public function test_subscription_active_scope(): void
    {
        $plan = Plan::factory()->create();
        Subscription::factory()->create(['status' => 'active', 'plan_id' => $plan->id]);
        Subscription::factory()->create(['status' => 'cancelled', 'plan_id' => $plan->id]);

        $this->assertCount(1, Subscription::active()->get());
    }

    public function test_subscription_expired_scope(): void
    {
        $plan = Plan::factory()->create();
        Subscription::factory()->expired()->create(['plan_id' => $plan->id]);
        Subscription::factory()->create(['status' => 'active', 'plan_id' => $plan->id]);

        $this->assertCount(1, Subscription::expired()->get());
    }

    public function test_subscription_cancelled_scope(): void
    {
        $plan = Plan::factory()->create();
        Subscription::factory()->cancelled()->create(['plan_id' => $plan->id]);
        Subscription::factory()->create(['status' => 'active', 'plan_id' => $plan->id]);

        $this->assertCount(1, Subscription::cancelled()->get());
    }

    public function test_subscription_pending_scope(): void
    {
        $plan = Plan::factory()->create();
        Subscription::factory()->pending()->create(['plan_id' => $plan->id]);
        Subscription::factory()->create(['status' => 'active', 'plan_id' => $plan->id]);

        $this->assertCount(1, Subscription::pending()->get());
    }

    public function test_subscription_index_returns_paginated_results(): void
    {
        $plan = Plan::factory()->create();
        Subscription::factory()->count(3)->create(['plan_id' => $plan->id]);

        $response = $this->getJson('/admin/api/subscriptions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'subscriptions' => [],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_subscription_index_filters_by_status(): void
    {
        $plan = Plan::factory()->create();
        Subscription::factory()->create(['status' => 'active', 'plan_id' => $plan->id]);
        Subscription::factory()->create(['status' => 'cancelled', 'plan_id' => $plan->id]);

        $response = $this->getJson('/admin/api/subscriptions?status=active');

        $response->assertStatus(200);
        $subscriptions = $response->json('subscriptions');
        foreach ($subscriptions as $sub) {
            $this->assertEquals('active', $sub['status']);
        }
    }

    public function test_subscription_index_filters_by_plan_id(): void
    {
        $plan1 = Plan::factory()->create();
        $plan2 = Plan::factory()->create();
        Subscription::factory()->count(2)->create(['plan_id' => $plan1->id]);
        Subscription::factory()->create(['plan_id' => $plan2->id]);

        $response = $this->getJson('/admin/api/subscriptions?plan_id='.$plan1->id);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('subscriptions'));
    }

    public function test_subscription_index_searches_by_tenant_name(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['name' => 'Unique Corp']);
        Subscription::factory()->create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id]);
        Subscription::factory()->count(2)->create(['plan_id' => $plan->id]);

        $response = $this->getJson('/admin/api/subscriptions?search=Unique');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('subscriptions'));
    }

    public function test_subscription_show_returns_subscription(): void
    {
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);

        $response = $this->getJson("/admin/api/subscriptions/{$subscription->id}");

        $response->assertStatus(200)
            ->assertJsonPath('subscription.id', $subscription->id);
    }

    public function test_subscription_show_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson('/admin/api/subscriptions/99999');

        $response->assertStatus(404);
    }

    public function test_subscription_store_creates_subscription(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create();

        $response = $this->postJson('/admin/api/subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'starts_at' => now()->format('Y-m-d'),
            'status' => 'active',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Subscription created successfully');

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }

    public function test_subscription_store_validates_required_fields(): void
    {
        $response = $this->postJson('/admin/api/subscriptions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tenant_id', 'plan_id', 'starts_at', 'status']);
    }

    public function test_subscription_store_validates_tenant_exists(): void
    {
        $response = $this->postJson('/admin/api/subscriptions', [
            'tenant_id' => 'nonexistent',
            'plan_id' => 99999,
            'starts_at' => now()->format('Y-m-d'),
            'status' => 'active',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tenant_id', 'plan_id']);
    }

    public function test_subscription_store_validates_status_enum(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create();

        $response = $this->postJson('/admin/api/subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'starts_at' => now()->format('Y-m-d'),
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_subscription_store_validates_ends_at_after_starts_at(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create();

        $response = $this->postJson('/admin/api/subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'starts_at' => now()->format('Y-m-d'),
            'ends_at' => now()->subDay()->format('Y-m-d'),
            'status' => 'active',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ends_at']);
    }

    public function test_subscription_update_modifies_subscription(): void
    {
        $plan = Plan::factory()->create();
        $newPlan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);

        $response = $this->putJson("/admin/api/subscriptions/{$subscription->id}", [
            'plan_id' => $newPlan->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Subscription updated successfully');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'plan_id' => $newPlan->id,
        ]);
    }

    public function test_subscription_destroy_deletes_subscription(): void
    {
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);

        $response = $this->deleteJson("/admin/api/subscriptions/{$subscription->id}");

        $response->assertStatus(204);
        $this->assertModelMissing($subscription);
    }

    public function test_subscription_destroy_returns_404_for_nonexistent(): void
    {
        $response = $this->deleteJson('/admin/api/subscriptions/99999');

        $response->assertStatus(404);
    }

    public function test_subscription_store_without_permission_returns_403(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create();

        $regularAdmin = AdminUser::factory()->create();
        $regularRole = Role::firstOrCreate(['name' => 'regular', 'guard_name' => 'admin']);
        $regularAdmin->assignRole('regular');
        $this->actingAs($regularAdmin, 'admin');

        $response = $this->postJson('/admin/api/subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'starts_at' => now()->format('Y-m-d'),
            'status' => 'active',
        ]);

        $response->assertStatus(403);
    }

    public function test_subscription_index_without_permission_returns_403(): void
    {
        $regularAdmin = AdminUser::factory()->create();
        $regularRole = Role::firstOrCreate(['name' => 'regular', 'guard_name' => 'admin']);
        $regularAdmin->assignRole('regular');
        $this->actingAs($regularAdmin, 'admin');

        $response = $this->getJson('/admin/api/subscriptions');

        $response->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->getJson('/admin/api/subscriptions');
        $response->assertStatus(401);
    }

    public function test_database_has_subscriptions_after_tenant_seeder(): void
    {
        $plan = Plan::where('slug', 'free')->first() ?? Plan::factory()->create(['slug' => 'free']);

        $tenant = Tenant::create([
            'id' => 'test-seeder-'.uniqid(),
            'name' => 'Seeder Test',
            'email' => 'seeder@test.com',
            'status' => 'Active',
            'plan_id' => $plan->id,
        ]);

        Artisan::call('db:seed', [
            '--class' => TenantSeeder::class,
            '--force' => true,
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => 'empresa-abc',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => 'tienda-xyz',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => 'consultoria-123',
            'status' => 'active',
        ]);
    }

    public function test_subscription_belongs_to_tenant(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $subscription = Subscription::createForTenant($tenant, $plan, 'active');

        $this->assertTrue($subscription->tenant->is($tenant));
    }

    public function test_subscription_belongs_to_plan(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $subscription = Subscription::createForTenant($tenant, $plan, 'active');

        $this->assertTrue($subscription->plan->is($plan));
    }

    public function test_subscription_tenant_status_missing_when_tenant_deleted_permanently(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $subscription = Subscription::createForTenant($tenant, $plan, 'active');

        $tenantId = $tenant->id;
        $tenant->forceDelete();

        $response = $this->getJson("/admin/api/subscriptions/{$subscription->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('subscription.tenant_status', 'missing');
        $response->assertJsonPath('subscription.tenant_name', 'Missing Tenant');
        $response->assertJsonPath('subscription.tenant_id', $tenantId);
    }

    public function test_subscription_tenant_status_deleted_when_tenant_soft_deleted(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $subscription = Subscription::createForTenant($tenant, $plan, 'active');

        $tenant->delete();

        $response = $this->getJson("/admin/api/subscriptions/{$subscription->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('subscription.tenant_status', 'deleted');
        $response->assertJsonPath('subscription.tenant_name', 'Deleted Tenant');
    }

    public function test_subscription_tenant_status_active_when_tenant_exists(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id, 'name' => 'Living Corp']);
        $subscription = Subscription::createForTenant($tenant, $plan, 'active');

        $response = $this->getJson("/admin/api/subscriptions/{$subscription->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('subscription.tenant_status', 'active');
        $response->assertJsonPath('subscription.tenant_name', 'Living Corp');
    }

    public function test_subscription_tenant_status_restricted_without_manage_tenants(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $subscription = Subscription::createForTenant($tenant, $plan, 'active');

        $regularAdmin = AdminUser::factory()->create();
        $regularRole = Role::firstOrCreate(['name' => 'regular', 'guard_name' => 'admin']);
        $permission = Permission::firstOrCreate(['name' => 'manage subscriptions', 'guard_name' => 'admin']);
        $regularRole->syncPermissions([$permission]);
        $regularAdmin->assignRole('regular');
        $this->actingAs($regularAdmin, 'admin');

        $response = $this->getJson("/admin/api/subscriptions/{$subscription->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('subscription.tenant_status', 'restricted');
        $response->assertJsonPath('subscription.tenant_name', 'Restricted');
    }

    public function test_subscription_index_returns_tenant_status_for_each_type(): void
    {
        $plan = Plan::factory()->create();

        $liveTenant = Tenant::factory()->create(['plan_id' => $plan->id, 'name' => 'Live Co']);
        $sub1 = Subscription::createForTenant($liveTenant, $plan, 'active');

        $deletedTenant = Tenant::factory()->create(['plan_id' => $plan->id, 'name' => 'Gone Co']);
        $sub2 = Subscription::createForTenant($deletedTenant, $plan, 'active');
        $deletedTenant->delete();

        $missingTenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $sub3 = Subscription::createForTenant($missingTenant, $plan, 'active');
        $missingTenant->forceDelete();

        $response = $this->getJson('/admin/api/subscriptions');

        $response->assertStatus(200);

        $subscriptions = $response->json('subscriptions');
        $subsById = collect($subscriptions)->keyBy('id');

        $this->assertEquals('active', $subsById[$sub1->id]['tenant_status']);
        $this->assertEquals('Live Co', $subsById[$sub1->id]['tenant_name']);

        $this->assertEquals('deleted', $subsById[$sub2->id]['tenant_status']);
        $this->assertEquals('Deleted Tenant', $subsById[$sub2->id]['tenant_name']);

        $this->assertEquals('missing', $subsById[$sub3->id]['tenant_status']);
        $this->assertEquals('Missing Tenant', $subsById[$sub3->id]['tenant_name']);
    }
}
