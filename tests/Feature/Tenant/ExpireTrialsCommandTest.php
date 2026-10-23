<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireTrialsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_suspends_tenants_whose_trial_has_ended(): void
    {
        $plan = Plan::factory()->create();

        $expired = Tenant::factory()->create([
            'status' => 'Trial',
            'plan_id' => $plan->id,
            'trial_ends_at' => now()->subDay(),
        ]);
        Subscription::factory()->for($expired)->for($plan)->create(['status' => 'active']);

        $this->artisan('tenants:expire-trials')->assertExitCode(0);

        $this->assertSame('Suspended', $expired->fresh()->status);
        $this->assertSame('cancelled', $expired->subscriptions()->first()->status);
    }

    public function test_command_suspends_trial_tenants_without_an_end_date(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => 'Trial',
            'trial_ends_at' => null,
        ]);

        $this->artisan('tenants:expire-trials')->assertExitCode(0);

        $this->assertSame('Suspended', $tenant->fresh()->status);
    }

    public function test_command_leaves_running_trials_and_non_trial_tenants_untouched(): void
    {
        $runningTrial = Tenant::factory()->create([
            'status' => 'Trial',
            'trial_ends_at' => now()->addDays(5),
        ]);
        $activeWithPastTrialDate = Tenant::factory()->create([
            'status' => 'Active',
            'trial_ends_at' => now()->subMonth(),
        ]);

        $this->artisan('tenants:expire-trials')->assertExitCode(0);

        $this->assertSame('Trial', $runningTrial->fresh()->status);
        $this->assertSame('Active', $activeWithPastTrialDate->fresh()->status);
    }

    public function test_dry_run_reports_but_does_not_suspend(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => 'Trial',
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->artisan('tenants:expire-trials', ['--dry-run' => true])
            ->expectsOutputToContain('[DRY-RUN] Would suspend tenant')
            ->assertExitCode(0);

        $this->assertSame('Trial', $tenant->fresh()->status);
    }
}
