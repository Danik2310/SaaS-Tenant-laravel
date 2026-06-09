<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class TenantLifecycleTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

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
        $tenant = Tenant::factory()->create(['status' => 'Deleted', 'plan_id' => $plan->id]);

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
        $tenant = Tenant::factory()->create(['status' => 'Active', 'plan_id' => $plan->id]);

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
        $user = \App\Models\AdminUser::factory()->create();
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
            ->assertJsonStructure(['tenants', 'total', 'meta']);
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
        parent::tearDown();
    }
}
