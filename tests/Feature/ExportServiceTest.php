<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Shared\Services\ExportService;
use Database\Seeders\CentralRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CentralRolePermissionSeeder::class);

        $dir = Storage::disk('local')->path('exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->service = app(ExportService::class);

        // Authenticate as super-admin so auth()->can() checks (e.g. tenant_name gating) work
        $admin = AdminUser::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin, 'admin');
    }

    protected function tearDown(): void
    {
        $files = glob(Storage::disk('local')->path('exports/*'));
        foreach ($files ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    // ──────────────────────────────────────────────
    //  Helper
    // ──────────────────────────────────────────────

    private function createTenant(): Tenant
    {
        // Tenant::create() always writes to the 'mysql' connection — stancl's
        // CentralConnection trait pins it to config('tenancy.database.central_connection')
        // (= 'mysql'), ignoring on('mysql_central'). withoutEvents() also
        // suppresses the CREATE DATABASE hook that other tests rely on to
        // implicitly commit (MySQL DDL auto-commits) and release the tenant row
        // for mysql_central's FK check. Without a commit, the Subscription FK
        // check on the uncommitted tenant row deadlocks with "1205 Lock wait
        // timeout". Commit explicitly so the row is visible on both connections.
        $tenant = Tenant::withoutEvents(function () {
            $tenant = Tenant::create([
                'id' => 'test-'.uniqid(),
                'name' => 'Test Tenant',
                'email' => 'tenant@example.com',
                'status' => 'active',
            ]);

            $tenant->domains()->create([
                'domain' => 'test.example.com',
            ]);

            return $tenant;
        });

        DB::connection('mysql')->commit();

        return $tenant;
    }

    // ──────────────────────────────────────────────
    //  Basic export — structure
    // ──────────────────────────────────────────────

    public function test_export_tenants_returns_correct_structure(): void
    {
        $result = $this->service->export('tenants', 'csv', []);

        $this->assertArrayHasKey('filename', $result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('record_count', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertStringEndsWith('.csv', $result['filename']);
        $this->assertTrue(Storage::disk('local')->exists($result['path']));
    }

    public function test_export_tenants_includes_data(): void
    {
        $tenant = $this->createTenant();

        $result = $this->service->export('tenants', 'csv', []);

        $this->assertGreaterThanOrEqual(1, $result['record_count']);
    }

    public function test_export_staff_includes_data(): void
    {
        AdminUser::factory()->count(3)->create();

        $result = $this->service->export('staff', 'csv', []);

        $this->assertGreaterThanOrEqual(3, $result['record_count']);
    }

    public function test_export_plans_includes_data(): void
    {
        Plan::factory()->count(2)->create();

        $result = $this->service->export('plans', 'csv', []);

        $this->assertGreaterThanOrEqual(2, $result['record_count']);
    }

    public function test_export_subscriptions_includes_data(): void
    {
        $plan = Plan::factory()->create();
        $tenant = $this->createTenant();

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $result = $this->service->export('subscriptions', 'csv', []);

        $this->assertGreaterThanOrEqual(1, $result['record_count']);
    }

    public function test_export_activity_logs_includes_data(): void
    {
        activity()->log('Test activity');
        activity('tenant')->log('Another activity');

        $result = $this->service->export('activity-logs', 'csv', []);

        $this->assertGreaterThanOrEqual(1, $result['record_count']);
    }

    // ──────────────────────────────────────────────
    //  Format variants
    // ──────────────────────────────────────────────

    public function test_export_as_xlsx(): void
    {
        $result = $this->service->export('tenants', 'xlsx', []);

        $this->assertStringEndsWith('.xlsx', $result['filename']);
        $this->assertTrue(Storage::disk('local')->exists($result['path']));
    }

    public function test_export_without_data_still_creates_file(): void
    {
        $result = $this->service->export('plans', 'csv', []);

        $this->assertEquals(0, $result['record_count']);
        $this->assertTrue(Storage::disk('local')->exists($result['path']));
    }

    // ──────────────────────────────────────────────
    //  Column filtering
    // ──────────────────────────────────────────────

    public function test_export_with_column_filtering(): void
    {
        $result = $this->service->export('tenants', 'csv', ['id', 'name', 'status']);

        $this->assertTrue(Storage::disk('local')->exists($result['path']));
    }

    public function test_export_with_empty_columns_uses_defaults(): void
    {
        $result = $this->service->export('tenants', 'csv', []);

        $this->assertTrue(Storage::disk('local')->exists($result['path']));
    }

    // ──────────────────────────────────────────────
    //  Filters
    // ──────────────────────────────────────────────

    public function test_export_with_status_filter(): void
    {
        $tenant1 = $this->createTenant();
        $tenant1->update(['status' => 'active']);
        $tenant1->save();

        $result = $this->service->export('tenants', 'csv', [], ['status' => 'active']);

        $this->assertTrue(Storage::disk('local')->exists($result['path']));
    }

    public function test_export_with_date_range_filter(): void
    {
        $result = $this->service->export('tenants', 'csv', [], [
            'date_from' => '2024-01-01',
            'date_to' => '2026-12-31',
        ]);

        $this->assertTrue(Storage::disk('local')->exists($result['path']));
    }

    // ──────────────────────────────────────────────
    //  Cleanup
    // ──────────────────────────────────────────────

    public function test_cleanup_expired_removes_old_files(): void
    {
        $result = $this->service->export('tenants', 'csv', []);
        $createdFile = $result['path'];

        $this->assertTrue(Storage::disk('local')->exists($createdFile));

        $this->service->cleanupExpired();

        // File was just created, should NOT be removed
        $this->assertTrue(Storage::disk('local')->exists($createdFile));
    }

    // ──────────────────────────────────────────────
    //  Invalid entity
    // ──────────────────────────────────────────────

    public function test_unknown_entity_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->export('invalid-entity', 'csv', []);
    }
}
