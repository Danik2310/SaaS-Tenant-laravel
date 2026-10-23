<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Tenants\Events\TenantReactivated;
use App\Tenants\Events\TenantSuspended;
use App\Tenants\States\TenantStateManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class TenantStateManagerTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'Active' => ['Trial', 'Suspended', 'Deleted'],
        'Trial' => ['Active', 'Suspended', 'Cancelled', 'Deleted'],
        'Suspended' => ['Active', 'Deleted'],
        'Cancelled' => ['Active', 'Deleted'],
        'Deleted' => ['Active'],
    ];

    public function test_allowed_transitions_are_reported_for_every_status(): void
    {
        foreach (self::ALLOWED as $status => $expected) {
            $this->assertSame($expected, TenantStateManager::allowedTransitions($status));
        }
    }

    public function test_unknown_status_has_no_allowed_transitions(): void
    {
        $this->assertSame([], TenantStateManager::allowedTransitions('Nope'));
    }

    public function test_every_allowed_transition_succeeds(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'Active']);

        foreach (self::ALLOWED as $from => $targets) {
            foreach ($targets as $to) {
                $tenant->status = $from;
                $tenant->saveQuietly();

                TenantStateManager::transitionTo($tenant, $to);

                $this->assertSame($to, $tenant->fresh()->status, "Expected {$from} → {$to}");
            }
        }
    }

    public function test_every_disallowed_transition_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'Active']);
        $statuses = array_keys(self::ALLOWED);

        foreach ($statuses as $from) {
            foreach ($statuses as $to) {
                if (in_array($to, self::ALLOWED[$from], true)) {
                    continue;
                }

                $tenant->status = $from;
                $tenant->saveQuietly();

                try {
                    TenantStateManager::transitionTo($tenant, $to);
                    $this->fail("Expected the {$from} → {$to} transition to be rejected.");
                } catch (InvalidArgumentException) {
                    $this->assertSame($from, $tenant->fresh()->status);
                }
            }
        }
    }

    public function test_suspending_fires_the_suspended_event(): void
    {
        Event::fake([TenantSuspended::class]);
        $tenant = Tenant::factory()->create(['status' => 'Active']);

        TenantStateManager::transitionTo($tenant, 'Suspended');

        Event::assertDispatched(TenantSuspended::class);
    }

    public function test_reactivating_fires_the_reactivated_event(): void
    {
        Event::fake([TenantReactivated::class]);
        $tenant = Tenant::factory()->create(['status' => 'Suspended']);

        TenantStateManager::transitionTo($tenant, 'Active');

        Event::assertDispatched(TenantReactivated::class);
    }

    public function test_entering_a_trial_fires_no_lifecycle_event(): void
    {
        Event::fake([TenantSuspended::class, TenantReactivated::class]);
        $tenant = Tenant::factory()->create(['status' => 'Active']);

        TenantStateManager::transitionTo($tenant, 'Trial');

        Event::assertNotDispatched(TenantSuspended::class);
        Event::assertNotDispatched(TenantReactivated::class);
    }
}
