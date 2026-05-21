<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Models\Tenant;
use App\Models\TenantResourceUsage;
use App\Models\User;
use App\Observers\Tenant\UserObserver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserObserverTest extends TestCase
{
    protected Tenant $tenant;

    /** @var array<int, Tenant> */
    protected array $createdTenants = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        $this->createdTenants[] = $this->tenant;
        $this->initializeTenant($this->tenant);
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
    //  Created event
    // -------------------------------------------------------------------

    public function test_creating_user_increments_users_count(): void
    {
        User::factory()->create();

        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('users_count')
        );
    }

    public function test_multiple_users_created_produce_correct_count(): void
    {
        User::factory()->count(5)->create();

        $this->assertEquals(
            5,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('users_count')
        );
    }

    // -------------------------------------------------------------------
    //  Deleted event
    // -------------------------------------------------------------------

    public function test_deleting_user_decrements_users_count(): void
    {
        $user = User::factory()->create();
        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('users_count')
        );

        $user->delete();

        $this->assertEquals(
            0,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('users_count')
        );
    }

    public function test_multiple_creates_and_deletes_produce_correct_final_count(): void
    {
        $users = User::factory()->count(3)->create();
        $this->assertEquals(
            3,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('users_count')
        );

        $users[0]->delete();
        $this->assertEquals(
            2,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('users_count')
        );

        $users[1]->delete();
        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('users_count')
        );

        $users[2]->delete();
        $this->assertEquals(
            0,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('users_count')
        );
    }

    // -------------------------------------------------------------------
    //  No-op in central context
    // -------------------------------------------------------------------

    public function test_observer_does_nothing_outside_tenant_context(): void
    {
        $this->forgetTenant();
        $this->assertNull(tenant('id'));

        $user = User::factory()->make(['id' => 999]);

        $observer = new UserObserver;
        $observer->created($user);

        $this->assertEquals(0, TenantResourceUsage::count());
    }

    public function test_deleted_does_nothing_outside_tenant_context(): void
    {
        $this->forgetTenant();
        $this->assertNull(tenant('id'));

        $user = User::factory()->make(['id' => 999]);

        $observer = new UserObserver;
        $observer->deleted($user);

        $this->assertEquals(0, TenantResourceUsage::count());
    }

    // -------------------------------------------------------------------
    //  Tenant isolation
    // -------------------------------------------------------------------

    public function test_creating_user_in_tenant_a_does_not_affect_tenant_b(): void
    {
        $tenantA = $this->tenant;

        User::factory()->create();

        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $tenantA->id)->value('users_count')
        );

        $this->forgetTenant();

        $tenantB = $this->createTestTenant();
        $this->createdTenants[] = $tenantB;
        $this->initializeTenant($tenantB);

        User::factory()->create();

        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $tenantA->id)->value('users_count'),
            'Tenant A count should remain 1'
        );

        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $tenantB->id)->value('users_count'),
            'Tenant B count should be 1'
        );

        $this->assertEquals(2, TenantResourceUsage::count());
    }

    public function test_deleting_user_in_tenant_b_does_not_affect_tenant_a(): void
    {
        User::factory()->create();
        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('users_count')
        );

        $this->forgetTenant();

        $tenantB = $this->createTestTenant();
        $this->createdTenants[] = $tenantB;
        $this->initializeTenant($tenantB);

        $userB = User::factory()->create();
        $userB->delete();

        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('users_count'),
            'Tenant A count should remain 1 after delete in tenant B'
        );

        $this->assertEquals(
            0,
            TenantResourceUsage::where('tenant_id', $tenantB->id)->value('users_count'),
            'Tenant B count should be 0'
        );
    }

    // -------------------------------------------------------------------
    //  Rapid successive operations
    // -------------------------------------------------------------------

    public function test_rapid_successive_creates_and_deletes_produce_correct_count(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $u1->delete();
        $u3 = User::factory()->create();
        $u2->delete();

        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('users_count')
        );
    }
}
