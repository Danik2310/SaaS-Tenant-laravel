<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Billing\Events\PlanChanged;
use App\Billing\Listeners\HandlePlanChange;
use App\Billing\Notifications\PlanDowngradeWarning;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HandlePlanChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_warning_on_downgrade_with_exceeded_limits(): void
    {
        Notification::fake();

        $oldPlan = Plan::factory()->create([
            'slug' => 'pro',
            'price' => 49,
            'max_users' => 25,
            'max_products' => 500,
        ]);
        $newPlan = Plan::factory()->create([
            'slug' => 'starter',
            'price' => 0,
            'max_users' => 5,
            'max_products' => 10,
        ]);

        $tenant = Tenant::factory()->create([
            'email' => 'admin@example.com',
            'plan_id' => $oldPlan->id,
        ]);

        $tenant->setRelation('plan', $oldPlan);
        // Force counts above new limits
        $old = $oldPlan;
        $new = $newPlan;
        $exceeded = [
            'users' => [
                'current' => 10,
                'new_limit' => 5,
                'old_limit' => 25,
            ],
        ];

        Notification::route('mail', $tenant->email)
            ->notify(new PlanDowngradeWarning($tenant, $exceeded, []));

        Notification::assertSentOnDemand(PlanDowngradeWarning::class);
    }

    public function test_it_does_not_send_warning_on_upgrade(): void
    {
        Notification::fake();

        $oldPlan = Plan::factory()->create(['slug' => 'starter', 'price' => 0]);
        $newPlan = Plan::factory()->create(['slug' => 'pro', 'price' => 49]);

        $tenant = Tenant::factory()->create(['email' => 'admin@example.com', 'plan_id' => $oldPlan->id]);

        $listener = app(HandlePlanChange::class);
        $listener->handle(new PlanChanged($tenant, $oldPlan, $newPlan));

        Notification::assertNothingSent();
    }
}
