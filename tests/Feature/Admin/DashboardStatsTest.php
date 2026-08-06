<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    /**
     * Override parent's $connectionsToTransact to only wrap 'mysql'.
     *
     * The controller queries tenants (mysql), admin_users (mysql), and plans
     * (mysql_central). The SELECT queries don't cross FK constraints, but
     * Tenant::delete() triggers tenancy bootstrappers that issue
     * DROP DATABASE (MySQL DDL), which would commit the mysql_central
     * transaction and leave stale data for subsequent tests.
     *
     * With only 'mysql' in the transaction, the direct UPDATE used below
     * to set deleted_at stays within the mysql transaction. mysql_central
     * operations auto-commit, avoiding DDL-based transaction corruption.
     */
    protected $connectionsToTransact = ['mysql'];

    protected function setUp(): void
    {
        parent::setUp();
        // The stats endpoint caches per key; flush so test data is never stale.
        Cache::flush();
        // setUpAdminAuth() called inline in tests that need it.
    }

    /**
     * Create a Tenant record without triggering tenancy bootstrappers
     * (which would CREATE DATABASE — MySQL DDL — and commit transactions).
     */
    private function createTenantWithoutEvents(array $attributes = []): Tenant
    {
        return Tenant::withoutEvents(function () use ($attributes) {
            return Tenant::factory()->create($attributes);
        });
    }

    /**
     * Soft-delete a test tenant without triggering tenancy bootstrappers
     * that would DROP DATABASE (DDL — implicit MySQL commit).
     */
    private function softDeleteTenant(Tenant $tenant): void
    {
        Tenant::withoutEvents(function () use ($tenant) {
            $tenant->deleted_at = now();
            $tenant->save(['timestamps' => false]);
        });
    }

    public function test_stats_returns_correct_counts(): void
    {
        $this->setUpAdminAuth();

        // Create tenants with different statuses
        $this->createTenantWithoutEvents(['status' => 'Active']);
        $this->createTenantWithoutEvents(['status' => 'Active']);
        $this->createTenantWithoutEvents(['status' => 'Trial']);
        $this->createTenantWithoutEvents(['status' => 'Suspended']);
        // Soft-deleted tenant (for trashed count) — use helper to avoid DDL
        $deleted = $this->createTenantWithoutEvents(['status' => 'Deleted']);
        $this->softDeleteTenant($deleted);

        // Create staff / admin users
        AdminUser::factory()->count(2)->create(['is_active' => true]);
        AdminUser::factory()->create(['is_active' => false]);

        // Create plans
        Plan::factory()->count(2)->create();

        $response = $this->getJson('/admin/api/dashboard-stats');

        $response->assertOk()
            ->assertJsonStructure([
                'stats' => [
                    'total_tenants',
                    'active_tenants',
                    'trial_tenants',
                    'suspended_tenants',
                    'deleted_tenants',
                    'total_staff',
                    'active_staff',
                    'total_plans',
                ],
                'recent_tenants',
                'tenants_by_month',
                'status_distribution',
            ]);

        $response->assertJson([
            'stats' => [
                'total_tenants' => 5,
                'active_tenants' => 2,
                'trial_tenants' => 1,
                'suspended_tenants' => 1,
                'deleted_tenants' => 1,
                'total_staff' => 4, // 1 from setUpAdminAuth() + 3 created above
                'active_staff' => 3,
                'total_plans' => 2,
            ],
        ]);
    }

    public function test_recent_tenants_returns_latest_five(): void
    {
        $this->setUpAdminAuth();

        $now = now();
        // Create 10 tenants with staggered timestamps so ordering is deterministic
        foreach (range(1, 10) as $i) {
            $this->createTenantWithoutEvents([
                'name' => 'Tenant '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'created_at' => (clone $now)->addSeconds($i),
            ]);
        }

        $response = $this->getJson('/admin/api/dashboard-stats');

        $response->assertOk();
        $recent = $response->json('recent_tenants');

        $this->assertCount(5, $recent);
        // Most recent 5 should be Tenant 10 through Tenant 06
        $this->assertEquals('Tenant 10', $recent[0]['name']);
        $this->assertEquals('Tenant 09', $recent[1]['name']);
        $this->assertEquals('Tenant 06', $recent[4]['name']);
    }

    public function test_tenants_by_month_returns_grouped_counts(): void
    {
        $this->setUpAdminAuth();

        $this->createTenantWithoutEvents(['created_at' => now()->subMonths(2)]);
        $this->createTenantWithoutEvents(['created_at' => now()->subMonths(2)]);
        $this->createTenantWithoutEvents(['created_at' => now()->subMonth()]);

        $response = $this->getJson('/admin/api/dashboard-stats');

        $response->assertOk();
        $byMonth = $response->json('tenants_by_month');

        $this->assertNotEmpty($byMonth);
        $months = array_map(fn ($m) => $m['month'], $byMonth);
        $this->assertContains(now()->subMonths(2)->format('Y-m'), $months);
        $this->assertContains(now()->subMonth()->format('Y-m'), $months);
    }

    public function test_status_distribution_is_correct(): void
    {
        $this->setUpAdminAuth();

        $this->createTenantWithoutEvents(['status' => 'Active']);
        $this->createTenantWithoutEvents(['status' => 'Active']);
        $this->createTenantWithoutEvents(['status' => 'Trial']);
        $this->createTenantWithoutEvents(['status' => 'Suspended']);
        $deleted = $this->createTenantWithoutEvents(['status' => 'Deleted']);
        $this->softDeleteTenant($deleted);

        $response = $this->getJson('/admin/api/dashboard-stats');

        $response->assertOk();
        $distribution = $response->json('status_distribution');

        $this->assertCount(4, $distribution);
        $this->assertEquals(['name' => 'Active', 'value' => 2], $distribution[0]);
        $this->assertEquals(['name' => 'Trial', 'value' => 1], $distribution[1]);
        $this->assertEquals(['name' => 'Suspended', 'value' => 1], $distribution[2]);
        $this->assertEquals(['name' => 'Deleted', 'value' => 1], $distribution[3]);
    }

    public function test_unauthenticated_request_is_blocked(): void
    {
        $response = $this->getJson('/admin/api/dashboard-stats');

        $response->assertStatus(401);
    }

    public function test_user_without_permission_is_blocked(): void
    {
        $regularAdmin = AdminUser::factory()->create();
        $this->actingAs($regularAdmin, 'admin');

        $response = $this->getJson('/admin/api/dashboard-stats');

        $response->assertStatus(403);
    }
}
