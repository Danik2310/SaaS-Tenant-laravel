<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\ResourceUsageHistory;
use App\Models\Tenant;
use App\Models\TenantResourceUsage;
use App\Tenants\Jobs\CollectTenantMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CollectTenantMetricsTest extends TestCase
{
    use RefreshDatabase;

    private ?Tenant $tenant = null;

    protected function tearDown(): void
    {
        if ($this->tenant) {
            $database = $this->tenant->database()->getName();
            $this->forgetTenant();
            DB::statement("DROP DATABASE IF EXISTS `{$database}`");
        }

        parent::tearDown();
    }

    public function test_job_records_current_usage_and_a_daily_history_snapshot(): void
    {
        $this->tenant = $this->createTestTenant();

        (new CollectTenantMetrics($this->tenant))->handle();

        $usage = TenantResourceUsage::where('tenant_id', $this->tenant->id)->first();

        $this->assertNotNull($usage);
        $this->assertSame(now()->toDateString(), $usage->collected_at->toDateString());
        $this->assertGreaterThanOrEqual(0, $usage->users_count);
        $this->assertGreaterThanOrEqual(0, $usage->db_size_kb);

        $history = ResourceUsageHistory::where('tenant_id', $this->tenant->id)->first();

        $this->assertNotNull($history);
        $this->assertSame(now()->toDateString(), $history->snapshot_date->toDateString());
    }

    public function test_job_updates_the_existing_row_instead_of_duplicating_it(): void
    {
        $this->tenant = $this->createTestTenant();

        (new CollectTenantMetrics($this->tenant))->handle();
        (new CollectTenantMetrics($this->tenant))->handle();

        $this->assertSame(1, TenantResourceUsage::where('tenant_id', $this->tenant->id)->count());
        $this->assertSame(1, ResourceUsageHistory::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_command_dispatches_a_job_for_each_active_tenant(): void
    {
        Queue::fake();

        Tenant::factory()->count(2)->create(['status' => 'Active']);
        Tenant::factory()->create(['status' => 'Suspended']);

        $this->artisan('tenants:collect-metrics')->assertExitCode(0);

        Queue::assertPushed(CollectTenantMetrics::class, 2);
    }

    public function test_command_honours_explicit_tenant_ids(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create(['status' => 'Suspended']);
        Tenant::factory()->create(['status' => 'Active']);

        $this->artisan('tenants:collect-metrics', ['--tenant' => [$tenant->id]])->assertExitCode(0);

        Queue::assertPushed(CollectTenantMetrics::class, 1);
    }
}
