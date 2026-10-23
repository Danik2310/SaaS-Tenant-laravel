<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\ResourceUsageHistory;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class TenantAuxiliaryEndpointsTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    public function test_tenants_list_returns_id_name_and_plan_id(): void
    {
        $this->setUpAdminAuth();

        $plan = Plan::factory()->create();
        $tenants = Tenant::factory()->count(2)->create(['plan_id' => $plan->id]);

        $response = $this->getJson('/admin/api/tenants-list');

        $response->assertOk()
            ->assertJsonCount(2, 'tenants')
            ->assertJsonStructure(['tenants' => [['id', 'name', 'plan_id']]]);

        $this->assertEqualsCanonicalizing(
            $tenants->pluck('id')->all(),
            collect($response->json('tenants'))->pluck('id')->all(),
        );
    }

    public function test_metrics_history_returns_only_snapshots_inside_the_default_window(): void
    {
        $this->setUpAdminAuth();
        $tenant = Tenant::factory()->create();

        $this->snapshot($tenant, now()->toDateString());
        $this->snapshot($tenant, now()->subDays(45)->toDateString());

        $response = $this->getJson("/admin/api/resource-usage/{$tenant->id}/history");

        $response->assertOk()->assertJsonCount(1, 'history');
        $this->assertStringStartsWith(
            now()->toDateString(),
            (string) $response->json('history.0.snapshot_date'),
        );
    }

    public function test_metrics_history_widens_with_the_days_parameter(): void
    {
        $this->setUpAdminAuth();
        $tenant = Tenant::factory()->create();

        $this->snapshot($tenant, now()->toDateString());
        $this->snapshot($tenant, now()->subDays(45)->toDateString());

        $this->getJson("/admin/api/resource-usage/{$tenant->id}/history?days=90")
            ->assertOk()
            ->assertJsonCount(2, 'history');
    }

    public function test_metrics_history_rejects_an_out_of_range_window(): void
    {
        $this->setUpAdminAuth();
        $tenant = Tenant::factory()->create();

        $this->getJson("/admin/api/resource-usage/{$tenant->id}/history?days=0")
            ->assertUnprocessable();

        $this->getJson("/admin/api/resource-usage/{$tenant->id}/history?days=91")
            ->assertUnprocessable();
    }

    private function snapshot(Tenant $tenant, string $date): void
    {
        ResourceUsageHistory::create([
            'tenant_id' => $tenant->id,
            'snapshot_date' => $date,
            'users_count' => 1,
            'products_count' => 2,
            'orders_count' => 3,
            'storage_kb' => 4,
            'db_size_kb' => 5,
        ]);
    }
}
