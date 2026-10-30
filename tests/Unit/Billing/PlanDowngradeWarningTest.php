<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Billing\Notifications\PlanDowngradeWarning;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanDowngradeWarningTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_can_be_constructed(): void
    {
        $tenant = Tenant::factory()->make(['name' => 'Acme']);
        $notification = new PlanDowngradeWarning($tenant, ['users' => ['current' => 10, 'new_limit' => 5, 'old_limit' => 25]], ['api_access']);

        $this->assertInstanceOf(PlanDowngradeWarning::class, $notification);
        $this->assertSame(['mail'], $notification->via($tenant));
    }
}
