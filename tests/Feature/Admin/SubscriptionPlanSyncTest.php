<?php

namespace Tests\Feature\Admin;

use App\Events\PlanChanged;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class SubscriptionPlanSyncTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();
    }

    public function test_subscription_update_syncs_tenant_plan_id(): void
    {
        $oldPlan = Plan::factory()->create(['slug' => 'starter']);
        $newPlan = Plan::factory()->create(['slug' => 'pro']);
        $tenant = Tenant::factory()->create(['plan_id' => $oldPlan->id]);
        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $oldPlan->id,
        ]);

        $response = $this->putJson("/admin/api/subscriptions/{$subscription->id}", [
            'plan_id' => $newPlan->id,
        ]);

        $response->assertStatus(200);

        $tenant->refresh();
        $this->assertEquals($newPlan->id, $tenant->plan_id);
    }

    public function test_subscription_update_dispatches_plan_changed_event(): void
    {
        Event::fake();

        $oldPlan = Plan::factory()->create(['slug' => 'starter']);
        $newPlan = Plan::factory()->create(['slug' => 'pro']);
        $tenant = Tenant::factory()->create(['plan_id' => $oldPlan->id]);
        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $oldPlan->id,
        ]);

        $this->putJson("/admin/api/subscriptions/{$subscription->id}", [
            'plan_id' => $newPlan->id,
        ]);

        Event::assertDispatched(PlanChanged::class, function ($event) use ($tenant, $oldPlan, $newPlan) {
            return $event->tenant->id === $tenant->id
                && $event->oldPlan->id === $oldPlan->id
                && $event->newPlan->id === $newPlan->id;
        });
    }

    public function test_subscription_update_does_not_sync_when_plan_id_unchanged(): void
    {
        $plan = Plan::factory()->create(['slug' => 'starter']);
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
        ]);

        $this->putJson("/admin/api/subscriptions/{$subscription->id}", [
            'status' => 'cancelled',
        ]);

        $tenant->refresh();
        $this->assertEquals($plan->id, $tenant->plan_id);
    }

    public function test_subscription_update_does_not_dispatch_plan_changed_when_plan_id_unchanged(): void
    {
        Event::fake();

        $plan = Plan::factory()->create(['slug' => 'starter']);
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
        ]);

        $this->putJson("/admin/api/subscriptions/{$subscription->id}", [
            'status' => 'cancelled',
        ]);

        Event::assertNotDispatched(PlanChanged::class);
    }
}
