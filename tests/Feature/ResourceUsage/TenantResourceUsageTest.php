<?php

declare(strict_types=1);

namespace Tests\Feature\ResourceUsage;

use App\Models\Tenant;
use App\Models\TenantResourceUsage;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantResourceUsageTest extends TestCase
{
    protected Tenant $tenantA;

    protected Tenant $tenantB;

    /** @var array<int, Tenant> */
    protected array $createdTenants = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Set up two tenants for isolation testing
        $this->tenantA = $this->createTestTenant();
        $this->createdTenants[] = $this->tenantA;

        $this->forgetTenant();

        $this->tenantB = $this->createTestTenant();
        $this->createdTenants[] = $this->tenantB;

        // Start in tenant A context for most tests
        $this->initializeTenant($this->tenantA);
    }

    protected function tearDown(): void
    {
        $this->forgetTenant();

        // Manual cleanup: delete usage records and tenants, then drop databases.
        // We cannot rely on DatabaseTransactions because CREATE/DROP DATABASE are
        // DDL statements that auto-commit MySQL transactions.
        $tenantIds = [];
        foreach ($this->createdTenants as $t) {
            $tenantIds[] = $t->id;
            try {
                $dbName = $t->database()->getName();
                DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
            } catch (\Exception $e) {
                // Silently ignore — the DB may already be gone or inaccessible
            }
        }

        if ($tenantIds !== []) {
            TenantResourceUsage::whereIn('tenant_id', $tenantIds)->delete();
            Tenant::whereIn('id', $tenantIds)->delete();
        }

        parent::tearDown();
    }

    // -------------------------------------------------------------------
    //  firstOrCreate behavior
    // -------------------------------------------------------------------

    public function test_increment_count_creates_row_when_none_exists(): void
    {
        TenantResourceUsage::incrementCount($this->tenantA->id, 'users_count', 1);

        $row = TenantResourceUsage::where('tenant_id', $this->tenantA->id)->first();

        $this->assertNotNull($row, 'A row should have been created via firstOrCreate');
        $this->assertEquals(1, $row->users_count);
        $this->assertEquals(0, $row->products_count);
        $this->assertEquals(0, $row->orders_count);
        $this->assertEquals(0, $row->storage_kb);
        $this->assertEquals(0, $row->db_size_kb);
    }

    public function test_increment_count_uses_existing_row_on_subsequent_calls(): void
    {
        TenantResourceUsage::incrementCount($this->tenantA->id, 'users_count', 1);
        TenantResourceUsage::incrementCount($this->tenantA->id, 'users_count', 1);

        $row = TenantResourceUsage::where('tenant_id', $this->tenantA->id)->first();

        $this->assertNotNull($row);
        $this->assertEquals(2, $row->users_count);
        $this->assertEquals(0, $row->products_count);

        $this->assertEquals(1, TenantResourceUsage::where('tenant_id', $this->tenantA->id)->count());
    }

    // -------------------------------------------------------------------
    //  Delta behavior
    // -------------------------------------------------------------------

    public function test_increment_count_with_positive_delta(): void
    {
        TenantResourceUsage::incrementCount($this->tenantA->id, 'orders_count', 5);

        $this->assertEquals(
            5,
            TenantResourceUsage::where('tenant_id', $this->tenantA->id)->value('orders_count')
        );
    }

    public function test_increment_count_with_negative_delta(): void
    {
        TenantResourceUsage::incrementCount($this->tenantA->id, 'products_count', 10);
        TenantResourceUsage::incrementCount($this->tenantA->id, 'products_count', -3);

        $this->assertEquals(
            7,
            TenantResourceUsage::where('tenant_id', $this->tenantA->id)->value('products_count')
        );
    }

    public function test_increment_count_can_decrement_below_zero(): void
    {
        TenantResourceUsage::incrementCount($this->tenantA->id, 'users_count', -5);

        $this->assertEquals(
            -5,
            TenantResourceUsage::where('tenant_id', $this->tenantA->id)->value('users_count')
        );
    }

    // -------------------------------------------------------------------
    //  Multiple column tracking
    // -------------------------------------------------------------------

    public function test_tracking_multiple_counters_independently(): void
    {
        TenantResourceUsage::incrementCount($this->tenantA->id, 'users_count', 3);
        TenantResourceUsage::incrementCount($this->tenantA->id, 'products_count', 7);
        TenantResourceUsage::incrementCount($this->tenantA->id, 'orders_count', 2);

        $row = TenantResourceUsage::where('tenant_id', $this->tenantA->id)->first();

        $this->assertEquals(3, $row->users_count);
        $this->assertEquals(7, $row->products_count);
        $this->assertEquals(2, $row->orders_count);
    }

    // -------------------------------------------------------------------
    //  Tenant isolation
    // -------------------------------------------------------------------

    public function test_increment_count_affects_only_specified_tenant(): void
    {
        TenantResourceUsage::incrementCount($this->tenantA->id, 'users_count', 5);
        TenantResourceUsage::incrementCount($this->tenantB->id, 'users_count', 3);

        $this->assertEquals(
            5,
            TenantResourceUsage::where('tenant_id', $this->tenantA->id)->value('users_count'),
            'Tenant A should have 5 users'
        );

        $this->assertEquals(
            3,
            TenantResourceUsage::where('tenant_id', $this->tenantB->id)->value('users_count'),
            'Tenant B should have 3 users'
        );
    }

    public function test_increment_count_on_tenant_b_does_not_alter_tenant_a_row(): void
    {
        TenantResourceUsage::incrementCount($this->tenantA->id, 'storage_kb', 100);

        TenantResourceUsage::incrementCount($this->tenantB->id, 'storage_kb', 200);

        $this->assertEquals(
            100,
            TenantResourceUsage::where('tenant_id', $this->tenantA->id)->value('storage_kb'),
            'Tenant A storage_kb should remain 100'
        );

        $this->assertEquals(
            200,
            TenantResourceUsage::where('tenant_id', $this->tenantB->id)->value('storage_kb'),
            'Tenant B storage_kb should be 200'
        );

        $this->assertEquals(2, TenantResourceUsage::count());
    }

    // -------------------------------------------------------------------
    //  Database connection verification
    // -------------------------------------------------------------------

    public function test_tenant_resource_usage_uses_central_connection(): void
    {
        $model = new TenantResourceUsage;

        $this->assertEquals(
            'mysql_central',
            $model->getConnectionName(),
            'TenantResourceUsage must use the central mysql connection'
        );
    }

    // -------------------------------------------------------------------
    //  firstOrCreate does not reset counters on re-run
    // -------------------------------------------------------------------

    public function test_second_increment_does_not_reset_other_counters(): void
    {
        TenantResourceUsage::incrementCount($this->tenantA->id, 'users_count', 3);
        TenantResourceUsage::incrementCount($this->tenantA->id, 'products_count', 7);

        $row = TenantResourceUsage::where('tenant_id', $this->tenantA->id)->first();

        $this->assertEquals(3, $row->users_count);
        $this->assertEquals(7, $row->products_count);
    }
}
