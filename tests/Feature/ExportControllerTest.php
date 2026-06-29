<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Permission;
use Database\Seeders\CentralRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CentralRolePermissionSeeder::class);

        $dir = Storage::disk('local')->path('exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
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
    //  Helpers
    // ──────────────────────────────────────────────

    private function authenticateAsSuperAdmin(): void
    {
        $admin = AdminUser::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin, 'admin');
    }

    private function authenticateAsUserWithPermission(string $permissionName): AdminUser
    {
        $admin = AdminUser::factory()->create();
        $permission = Permission::findByName($permissionName, 'admin');
        $admin->givePermissionTo($permission);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    // ──────────────────────────────────────────────
    //  Authentication — guest
    // ──────────────────────────────────────────────

    public function test_guest_cannot_export(): void
    {
        $this->postJson('/admin/api/export/tenants')
            ->assertUnauthorized();
    }

    public function test_guest_cannot_download(): void
    {
        $this->getJson('/admin/api/export/download/test.csv')
            ->assertUnauthorized();
    }

    // ──────────────────────────────────────────────
    //  Super-admin — happy path (all entities)
    // ──────────────────────────────────────────────

    public function test_super_admin_can_export_tenants(): void
    {
        $this->authenticateAsSuperAdmin();

        $response = $this->postJson('/admin/api/export/tenants', [
            'format' => 'csv',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['filename', 'entity', 'format', 'record_count', 'expires_at'],
                'message',
            ])
            ->assertJsonPath('data.entity', 'tenants')
            ->assertJsonPath('data.format', 'csv');

        $filename = $response->json('data.filename');
        $this->assertTrue(
            Storage::disk('local')->exists('exports/'.$filename),
            "Export file {$filename} was not created."
        );
    }

    public function test_super_admin_can_export_tenants_as_xlsx(): void
    {
        $this->authenticateAsSuperAdmin();

        $response = $this->postJson('/admin/api/export/tenants', [
            'format' => 'xlsx',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.format', 'xlsx');
    }

    public function test_super_admin_can_export_subscriptions(): void
    {
        $this->authenticateAsSuperAdmin();

        $this->postJson('/admin/api/export/subscriptions')
            ->assertOk()
            ->assertJsonPath('data.entity', 'subscriptions');
    }

    public function test_super_admin_can_export_staff(): void
    {
        $this->authenticateAsSuperAdmin();

        $this->postJson('/admin/api/export/staff')
            ->assertOk()
            ->assertJsonPath('data.entity', 'staff');
    }

    public function test_super_admin_can_export_plans(): void
    {
        $this->authenticateAsSuperAdmin();

        $this->postJson('/admin/api/export/plans')
            ->assertOk()
            ->assertJsonPath('data.entity', 'plans');
    }

    public function test_super_admin_can_export_activity_logs(): void
    {
        $this->authenticateAsSuperAdmin();

        $this->postJson('/admin/api/export/activity-logs')
            ->assertOk()
            ->assertJsonPath('data.entity', 'activity-logs');
    }

    // ──────────────────────────────────────────────
    //  Permission granularity
    // ──────────────────────────────────────────────

    public function test_user_with_manage_subscriptions_can_export_subscriptions(): void
    {
        $this->authenticateAsUserWithPermission('manage subscriptions');

        $this->postJson('/admin/api/export/subscriptions')
            ->assertOk();
    }

    public function test_user_with_manage_subscriptions_cannot_export_tenants(): void
    {
        $this->authenticateAsUserWithPermission('manage subscriptions');

        $this->postJson('/admin/api/export/tenants')
            ->assertForbidden();
    }

    public function test_user_with_manage_staff_cannot_export_tenants(): void
    {
        $this->authenticateAsUserWithPermission('manage staff');

        $this->postJson('/admin/api/export/tenants')
            ->assertForbidden();
    }

    public function test_user_with_manage_plans_cannot_export_tenants(): void
    {
        $this->authenticateAsUserWithPermission('manage plans');

        $this->postJson('/admin/api/export/tenants')
            ->assertForbidden();
    }

    public function test_user_with_view_activity_logs_cannot_export_tenants(): void
    {
        $this->authenticateAsUserWithPermission('view activity logs');

        $this->postJson('/admin/api/export/tenants')
            ->assertForbidden();
    }

    public function test_user_without_permissions_cannot_export_any_entity(): void
    {
        $admin = AdminUser::factory()->create();
        $this->actingAs($admin, 'admin');

        $entities = ['tenants', 'subscriptions', 'staff', 'plans', 'activity-logs'];
        foreach ($entities as $entity) {
            $this->postJson("/admin/api/export/{$entity}")
                ->assertForbidden();
        }
    }

    // ──────────────────────────────────────────────
    //  Download
    // ──────────────────────────────────────────────

    public function test_can_download_exported_file(): void
    {
        $this->authenticateAsSuperAdmin();

        $export = $this->postJson('/admin/api/export/tenants');
        $filename = $export->json('data.filename');

        $this->getJson("/admin/api/export/download/{$filename}")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_download_non_existent_file_returns_404(): void
    {
        $this->authenticateAsSuperAdmin();

        $this->getJson('/admin/api/export/download/nonexistent.csv')
            ->assertNotFound();
    }

    public function test_download_invalid_extension_returns_422(): void
    {
        $this->authenticateAsSuperAdmin();

        $this->getJson('/admin/api/export/download/hack.php')
            ->assertStatus(422);
    }

    public function test_guest_cannot_download_export(): void
    {
        $this->getJson('/admin/api/export/download/somefile.csv')
            ->assertUnauthorized();
    }

    // ──────────────────────────────────────────────
    //  Status
    // ──────────────────────────────────────────────

    public function test_status_returns_unknown_for_non_existent_job(): void
    {
        $this->authenticateAsSuperAdmin();

        $this->getJson('/admin/api/export/status/abc-123')
            ->assertOk()
            ->assertJsonPath('data.status', 'unknown');
    }

    public function test_status_returns_completed_for_valid_job(): void
    {
        $this->authenticateAsSuperAdmin();

        $jobId = (string) Str::uuid();
        Cache::store('global')->put("export:{$jobId}:status", 'completed', 60);

        $this->getJson("/admin/api/export/status/{$jobId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }
}
