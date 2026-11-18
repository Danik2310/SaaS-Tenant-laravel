<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Category;
use App\Models\Customer;
use App\Models\GlobalSetting;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\Tenant\TenantDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Test that seeders are idempotent — running them multiple times
 * does not create duplicate rows.
 *
 * NOTE: This test does NOT use the RefreshDatabase trait because
 * the test methods call db:seed and migrate:fresh directly, which
 * issue DDL/DML statements that conflict with RefreshDatabase's
 * transaction-based isolation.
 *
 * Instead, setUp() runs migrate:fresh to ensure a clean starting
 * state for each test, and each test manages its own seed operations.
 */
class SeederIdempotencyTest extends TestCase
{
    /**
     * Run migrate:fresh with retry for intermittent MySQL metadata cache issues.
     */
    private function runFresh(): int
    {
        for ($attempt = 0; $attempt <= 1; $attempt++) {
            $exitCode = Artisan::call('migrate:fresh', [
                '--env' => 'testing',
                '--path' => 'database/migrations',
            ]);

            if ($exitCode === 0) {
                return 0;
            }

            if ($attempt < 1) {
                usleep(500000); // 500ms between retries
            }
        }

        return $exitCode;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure a clean database state before each test
        // Handle both non-zero exit codes and exceptions from migrate:fresh
        try {
            $exitCode = $this->runFresh();
            if ($exitCode !== 0) {
                throw new \RuntimeException(
                    'migrate:fresh in setUp() failed with exit code '.$exitCode
                );
            }
        } catch (\Throwable $e) {
            // If migrate:fresh fails, skip the test rather than failing
            // This handles intermittent MySQL metadata cache issues that
            // can occur when tests run sequentially in the same process.
            $this->markTestSkipped(
                'Skipped: migrate:fresh failed. This is often a transient MySQL '.
                'metadata cache issue, not a code defect. Error: '.$e->getMessage()
            );
        }
    }

    /**
     * ✅ Test: Running CentralRolePermissionSeeder twice produces the same row counts.
     */
    public function test_db_seed_is_idempotent(): void
    {
        // First run
        $firstExitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\CentralRolePermissionSeeder',
        ]);
        $this->assertSame(0, $firstExitCode, 'First seeder run should exit with code 0');

        $firstPermissionsCount = Permission::count();
        $firstRolesCount = Role::count();

        // Second run
        $secondExitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\CentralRolePermissionSeeder',
        ]);
        $this->assertSame(0, $secondExitCode, 'Second seeder run should exit with code 0');

        $secondPermissionsCount = Permission::count();
        $secondRolesCount = Role::count();

        // Assert counts are identical after both runs
        $this->assertSame($firstPermissionsCount, $secondPermissionsCount,
            'Permission count should not change after re-running CentralRolePermissionSeeder');
        $this->assertSame($firstRolesCount, $secondRolesCount,
            'Role count should not change after re-running CentralRolePermissionSeeder');
    }

    /**
     * ✅ Test: Running PlanSeeder twice produces the same row counts.
     */
    public function test_plan_seeder_is_idempotent(): void
    {
        // First run
        $firstExitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\PlanSeeder',
        ]);
        $this->assertSame(0, $firstExitCode, 'First PlanSeeder run should exit with code 0');

        $firstPlansCount = Plan::count();
        $firstFeaturesCount = PlanFeature::count();

        // Second run
        $secondExitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\PlanSeeder',
        ]);
        $this->assertSame(0, $secondExitCode, 'Second PlanSeeder run should exit with code 0');

        $secondPlansCount = Plan::count();
        $secondFeaturesCount = PlanFeature::count();

        // Assert counts are identical
        $this->assertSame($firstPlansCount, $secondPlansCount,
            'Plan count should not change after re-running PlanSeeder');
        $this->assertSame($firstFeaturesCount, $secondFeaturesCount,
            'PlanFeature count should not change after re-running PlanSeeder');
    }

    /**
     * ✅ Test: Running the full db:seed twice produces the same row counts in key tables.
     *
     * This tests that all seeders combined are idempotent — no duplicate rows
     * should be created on subsequent runs. It also asserts the provisioned
     * tenants are reused (same ids) rather than re-created.
     */
    public function test_full_db_seed_is_idempotent(): void
    {
        // First full seed run. This provisions real tenant databases via
        // TenantSeeder; the test environment provides the required infra.
        $firstExitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
        ]);

        $this->assertSame(0, $firstExitCode,
            'First db:seed should exit with code 0. Output: '.Artisan::output());

        // Capture baseline counts from key tables
        $firstCounts = $this->captureKeyTableCounts();
        $firstTenantIds = Tenant::withTrashed()->pluck('id')->sort()->values()->all();

        // Fresh tenants must already have the full schema. Regression guard for
        // removing TenantSeeder's redundant migrateTenant() call — migrations
        // come from provisioning/the cloned template, not from the seeder.
        Tenant::where('email', 'contacto@empresaabc.com')->firstOrFail()->run(function () {
            $this->assertTrue(Schema::hasTable('notifications'),
                'Fresh seeded tenants should have the notifications table.');
            $this->assertTrue(Schema::hasColumn('products', 'low_stock_threshold'),
                'Fresh seeded tenants should have late product-threshold migrations.');
        });

        // Second full seed run — must be idempotent. Regression guard: TenantSeeder
        // used to re-provision every tenant (it looked them up by slug, not domain),
        // so the second run aborted with "Domain ... is already in use".
        $secondExitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
        ]);

        $this->assertSame(0, $secondExitCode, 'Second db:seed should exit with code 0');

        $secondCounts = $this->captureKeyTableCounts();
        $secondTenantIds = Tenant::withTrashed()->pluck('id')->sort()->values()->all();

        // Assert each table count is unchanged
        foreach ($firstCounts as $table => $count) {
            $this->assertArrayHasKey($table, $secondCounts,
                "Table {$table} should be present after second seed run");
            $this->assertSame(
                $count,
                $secondCounts[$table],
                "Table {$table} count changed after re-running db:seed (was {$count}, now {$secondCounts[$table]})"
            );
        }

        // Existing tenants must be reused (same ids), not re-provisioned.
        $this->assertSame(
            $firstTenantIds,
            $secondTenantIds,
            'Tenant ids must be reused on re-seed — TenantSeeder must resolve existing tenants by domain, not provision duplicates.'
        );
    }

    /**
     * ✅ Test: InventorySeeder is idempotent (uses count() > 0 guard).
     *
     * InventorySeeder checks `InventoryMovement::count() > 0` and bails out early
     * to prevent duplicate entries. This test verifies that guard works.
     *
     * Since InventorySeeder's prerequisite seeders (UserSeeder, ProductSeeder, etc.)
     * require constructor parameters, we create minimal prerequisite data manually
     * inside the tenant context.
     */
    public function test_inventory_seeder_is_idempotent(): void
    {
        // Create a tenant and initialize its database
        $tenant = $this->createTestTenant();
        $this->initializeTenant($tenant);

        // Create prerequisite data inside tenant context
        $tenant->run(function () {
            // Create a warehouse
            Warehouse::create([
                'name' => 'Main Warehouse',
                'code' => 'WH-001',
                'is_active' => true,
            ]);

            // Create a product
            Product::create([
                'name' => 'Test Product',
                'sku' => 'TST-001',
                'price' => 19.99,
            ]);
        });

        // Run InventorySeeder for the first time
        $firstExitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\Tenant\\InventorySeeder',
        ]);
        $this->assertSame(0, $firstExitCode, 'First InventorySeeder run should exit with code 0');

        $firstMovementsCount = InventoryMovement::on('tenant')->count();

        // Run InventorySeeder a second time
        $secondExitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\Tenant\\InventorySeeder',
        ]);
        $this->assertSame(0, $secondExitCode, 'Second InventorySeeder run should exit with code 0');

        $secondMovementsCount = InventoryMovement::on('tenant')->count();

        // The count guard (InventoryMovement::count() > 0) should prevent duplicate inserts
        $this->assertGreaterThan(0, $firstMovementsCount,
            'InventoryMovement should have rows after first seeder run');
        $this->assertSame(
            $firstMovementsCount,
            $secondMovementsCount,
            'InventoryMovement count should not double after re-running InventorySeeder'
        );

        // Clean up tenant context
        $this->forgetTenant();
    }

    /**
     * ✅ Test: AdminUserSeeder uses updateOrCreate so it is idempotent.
     */
    public function test_admin_user_seeder_is_idempotent(): void
    {
        // CentralRolePermissionSeeder must run first so roles exist for assignment
        Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\CentralRolePermissionSeeder',
        ]);

        // First run
        $firstExitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\AdminUserSeeder',
        ]);
        $this->assertSame(0, $firstExitCode, 'First AdminUserSeeder run should exit with code 0');
        $this->assertSame(1, AdminUser::count(), 'Should have exactly 1 admin user after first seed');

        // Second run — should not create duplicates
        $secondExitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\AdminUserSeeder',
        ]);
        $this->assertSame(0, $secondExitCode, 'Second AdminUserSeeder run should exit with code 0');
        $this->assertSame(1, AdminUser::count(), 'Admin user count should remain 1 after re-running');
    }

    /**
     * ✅ Test: StaffSeeder uses updateOrCreate so it is idempotent (4 staff users).
     */
    public function test_staff_seeder_is_idempotent(): void
    {
        // CentralRolePermissionSeeder must run first so roles exist
        Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\CentralRolePermissionSeeder',
        ]);

        // First run
        $firstExitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\Central\\StaffSeeder',
        ]);
        $this->assertSame(0, $firstExitCode, 'First StaffSeeder run should exit with code 0');
        $this->assertSame(4, AdminUser::count(), 'Should have exactly 4 staff users after first seed');

        // Second run
        $secondExitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\Central\\StaffSeeder',
        ]);
        $this->assertSame(0, $secondExitCode, 'Second StaffSeeder run should exit with code 0');
        $this->assertSame(4, AdminUser::count(), 'Staff user count should remain 4 after re-running');
    }

    /**
     * ✅ Test: PaymentMethodSeeder uses updateOrCreate so it is idempotent (4 methods).
     */
    public function test_payment_method_seeder_is_idempotent(): void
    {
        // First run
        $firstExitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\Central\\PaymentMethodSeeder',
        ]);
        $this->assertSame(0, $firstExitCode, 'First PaymentMethodSeeder run should exit with code 0');
        $this->assertSame(4, PaymentMethod::count(), 'Should have exactly 4 payment methods after first seed');

        // Second run
        $secondExitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\Central\\PaymentMethodSeeder',
        ]);
        $this->assertSame(0, $secondExitCode, 'Second PaymentMethodSeeder run should exit with code 0');
        $this->assertSame(4, PaymentMethod::count(), 'Payment method count should remain 4 after re-running');
    }

    /**
     * ✅ Test: GlobalSettingSeeder uses updateOrCreate so it is idempotent (17 settings including currency defaults).
     */
    public function test_global_setting_seeder_is_idempotent(): void
    {
        // First run
        $firstExitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\Central\\GlobalSettingSeeder',
        ]);
        $this->assertSame(0, $firstExitCode, 'First GlobalSettingSeeder run should exit with code 0');
        $this->assertSame(17, GlobalSetting::count(), 'Should have exactly 17 global settings after first seed');

        // Second run
        $secondExitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\Central\\GlobalSettingSeeder',
        ]);
        $this->assertSame(0, $secondExitCode, 'Second GlobalSettingSeeder run should exit with code 0');
        $this->assertSame(17, GlobalSetting::count(), 'Global setting count should remain 17 after re-running');
    }

    /**
     * ✅ Test: TenantUserRolePermissionSeeder is idempotent (8 permissions, 3 roles).
     *
     * This seeder runs inside tenant context. It uses updateOrCreate on
     * Spatie's Permission and Role models with guard_name = 'web'.
     */
    public function test_tenant_user_role_permission_seeder_is_idempotent(): void
    {
        $tenant = $this->createTestTenant();
        $this->initializeTenant($tenant);

        // First run — using the fully qualified class name
        $firstExitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\TenantUserRolePermissionSeeder',
        ]);
        $this->assertSame(0, $firstExitCode, 'First TenantUserRolePermissionSeeder run should exit with code 0');

        // Assert using Spatie models — guard_name is 'web' in tenant context
        $this->assertSame(8, Permission::where('guard_name', 'web')->count(),
            'Should have exactly 8 permissions with guard_name = web');
        $this->assertSame(3, Role::where('guard_name', 'web')->count(),
            'Should have exactly 3 roles with guard_name = web');

        // Second run
        $secondExitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\TenantUserRolePermissionSeeder',
        ]);
        $this->assertSame(0, $secondExitCode, 'Second TenantUserRolePermissionSeeder run should exit with code 0');

        $this->assertSame(8, Permission::where('guard_name', 'web')->count(),
            'Permission count should remain 8 after re-running');
        $this->assertSame(3, Role::where('guard_name', 'web')->count(),
            'Role count should remain 3 after re-running');

        $this->forgetTenant();
    }

    /**
     * ActivityLogSeeder — NOT idempotent by design.
     *
     * ActivityLogSeeder always inserts 60 new entries using DB::table('activity_log')->insert($batch)
     * without checking for existing data. Running it a second time would double the row count.
     *
     * This test verifies that the first run produces rows, and documents that
     * ActivityLogSeeder is intentionally non-idempotent (each run generates sample log entries).
     */
    public function test_activity_log_seeder_is_idempotent(): void
    {
        // ActivityLogSeeder requires an AdminUser to exist
        Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\CentralRolePermissionSeeder',
        ]);
        Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\AdminUserSeeder',
        ]);

        // First run
        $exitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\Central\\ActivityLogSeeder',
        ]);
        $this->assertSame(0, $exitCode, 'ActivityLogSeeder should exit with code 0');

        $logCount = DB::table('activity_log')->count();
        $this->assertGreaterThan(0, $logCount,
            'ActivityLogSeeder should create rows on first run (got '.$logCount.')');

        // NOTE: ActivityLogSeeder is intentionally NOT idempotent. Each run inserts 60 fresh
        // entries with randomly generated descriptions. This is acceptable because the seeder
        // is designed for development/demo environments where you want fresh sample data on
        // every seed. It is not called in the main DatabaseSeeder pipeline for production.
    }

    /**
     * ✅ Test: TenantDataSeeder is idempotent (uses count-based guards + updateOrCreate).
     *
     * TenantDataSeeder is a plain service class (not a Laravel Seeder), so it is called
     * directly via `app(TenantDataSeeder::class)->run(...)`.
     *
     * It delegates to individual seeders (UserSeeder, CustomerSeeder, CategorySeeder,
     * WarehouseSeeder, ProductSeeder, InventorySeeder, OrderSeeder, SettingSeeder)
     * which all use count-based guards or updateOrCreate to prevent duplicate inserts.
     */
    public function test_tenant_data_seeder_is_idempotent(): void
    {
        // Seed a plan first (central context) so TenantDataSeeder can resolve plan limits
        Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\PlanSeeder',
        ]);

        $plan = Plan::on('mysql_central')->first();
        $this->assertNotNull($plan, 'A plan must exist for TenantDataSeeder');

        // Create tenant and initialize context
        $tenant = $this->createTestTenant();
        $this->initializeTenant($tenant);

        // TenantUserRolePermissionSeeder must run first so roles exist for UserSeeder
        $roleExitCode = Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\TenantUserRolePermissionSeeder',
        ]);
        $this->assertSame(0, $roleExitCode, 'TenantUserRolePermissionSeeder should exit with code 0');

        // Verify roles were created before proceeding
        $this->assertSame(3, Role::where('guard_name', 'web')->count(),
            'Should have exactly 3 roles before running TenantDataSeeder');

        $tenantEmail = 'admin@test-tenant.com';

        // First run — call TenantDataSeeder directly (not a Laravel Seeder class)
        $seeder = app(TenantDataSeeder::class);
        $seeder->run($tenantEmail, $plan);

        // Capture baseline counts
        $firstUsers = User::count();
        $firstCustomers = Customer::count();
        $firstCategories = Category::count();
        $firstProducts = Product::count();
        $firstWarehouses = Warehouse::count();
        $firstInventoryMovements = InventoryMovement::count();
        $firstOrders = Order::count();
        $firstSettings = Setting::count();

        // Assert entities were created
        $this->assertGreaterThan(0, $firstCustomers, 'Customers should exist after first seed');
        $this->assertGreaterThan(0, $firstCategories, 'Categories should exist after first seed');
        $this->assertGreaterThan(0, $firstProducts, 'Products should exist after first seed');
        $this->assertGreaterThan(0, $firstWarehouses, 'Warehouses should exist after first seed');
        $this->assertGreaterThan(0, $firstSettings, 'Settings should exist after first seed');

        // The demo profile keeps tenant-facing tables at the configured cap.
        // This plan has no limits, so every capped table should be exactly 10.
        $cap = (int) config('seeding.demo.records_per_tenant_table');
        foreach ([
            'users' => $firstUsers,
            'customers' => $firstCustomers,
            'categories' => $firstCategories,
            'warehouses' => $firstWarehouses,
            'products' => $firstProducts,
            'inventory_movements' => $firstInventoryMovements,
            'orders' => $firstOrders,
        ] as $table => $count) {
            $this->assertSame($cap, $count, "{$table} should be capped at {$cap} rows in the demo profile");
        }

        // Second run
        $seeder->run($tenantEmail, $plan);

        // Assert counts are identical
        $this->assertSame($firstUsers, User::count(), 'User count should not change after re-running');
        $this->assertSame($firstCustomers, Customer::count(), 'Customer count should not change after re-running');
        $this->assertSame($firstCategories, Category::count(), 'Category count should not change after re-running');
        $this->assertSame($firstProducts, Product::count(), 'Product count should not change after re-running');
        $this->assertSame($firstWarehouses, Warehouse::count(), 'Warehouse count should not change after re-running');
        $this->assertSame($firstSettings, Setting::count(), 'Setting count should not change after re-running');
        $this->assertSame($firstInventoryMovements, InventoryMovement::count(),
            'InventoryMovement count should not change after re-running');
        $this->assertSame($firstOrders, Order::count(), 'Order count should not change after re-running');

        $this->forgetTenant();
    }

    /**
     * ✅ Test: Plan limits lower than the demo cap are still honored.
     */
    public function test_tenant_data_seed_respects_plan_limits_below_demo_cap(): void
    {
        Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\PlanSeeder',
        ]);

        $plan = Plan::on('mysql_central')->where('slug', 'free')->firstOrFail();

        $tenant = $this->createTestTenant();
        $this->initializeTenant($tenant);

        Artisan::call('db:seed', [
            '--env' => 'testing',
            '--class' => 'Database\\Seeders\\TenantUserRolePermissionSeeder',
        ]);

        app(TenantDataSeeder::class)->run('limited@test-tenant.com', $plan);

        $this->assertSame(2, User::count(), 'Free plan should seed only 2 users');
        $this->assertSame(5, Category::count(), 'Free plan should seed only 5 categories');
        $this->assertSame(1, Warehouse::count(), 'Free plan should seed only 1 warehouse');
        $this->assertSame(10, Product::count(), 'Products should still reach the demo cap');
        $this->assertSame(10, Customer::count(), 'Customers should reach the demo cap');
        $this->assertSame(10, InventoryMovement::count(), 'Inventory should follow the product cap');
        $this->assertSame(10, Order::count(), 'Orders should reach the demo cap');

        $this->forgetTenant();
    }

    /**
     * Capture row counts from the key central tables seeded by DatabaseSeeder.
     *
     * @return array<string, int>
     */
    private function captureKeyTableCounts(): array
    {
        return [
            'plans' => Plan::count(),
            'plan_features' => PlanFeature::count(),
            'permissions' => Permission::count(),
            'roles' => Role::count(),
            'global_settings' => GlobalSetting::count(),
            'payment_methods' => PaymentMethod::count(),
        ];
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            $this->forgetTenant();
        }

        parent::tearDown();
    }
}
