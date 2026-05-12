<?php

namespace Tests\Unit\Observers;

use App\Models\TenantResourceUsage;
use App\Models\User;
use App\Observers\Tenant\UserObserver;

class UserObserverTest extends ObserverTestCase
{
    // -----------------------------------------------------------------------
    //  Created event
    // -----------------------------------------------------------------------

    public function test_user_created_increments_users_count(): void
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

    // -----------------------------------------------------------------------
    //  Deleted event
    // -----------------------------------------------------------------------

    public function test_user_deleted_decrements_users_count(): void
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

    public function test_create_and_delete_users_produces_correct_final_count(): void
    {
        $users = User::factory()->count(3)->create();
        $this->assertEquals(
            3,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('users_count')
        );

        // Delete one, then two remain
        $users[0]->delete();
        $this->assertEquals(
            2,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('users_count')
        );

        // Delete another, one remains
        $users[1]->delete();
        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('users_count')
        );

        // Delete last
        $users[2]->delete();
        $this->assertEquals(
            0,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('users_count')
        );
    }

    // -----------------------------------------------------------------------
    //  No-op in central context
    // -----------------------------------------------------------------------

    public function test_observer_does_nothing_when_tenant_id_is_null(): void
    {
        // Ensure we are NOT in tenant context
        $this->forgetTenant();
        $this->assertNull(tenant('id'));

        // Create a user in memory (not persisted — the table doesn't exist centrally)
        $user = User::factory()->make(['id' => 999]);

        $observer = new UserObserver;
        $observer->created($user);

        // No row should have been inserted
        $this->assertEquals(0, TenantResourceUsage::count());
    }

    public function test_deleted_does_nothing_when_tenant_id_is_null(): void
    {
        $this->forgetTenant();
        $this->assertNull(tenant('id'));

        $user = User::factory()->make(['id' => 999]);

        $observer = new UserObserver;
        $observer->deleted($user);

        $this->assertEquals(0, TenantResourceUsage::count());
    }

    // -----------------------------------------------------------------------
    //  Tenant isolation
    // -----------------------------------------------------------------------

    public function test_creating_user_in_tenant_a_does_not_affect_tenant_b_counts(): void
    {
        $tenantA = $this->tenant;

        // Create a user in tenant A
        User::factory()->create();

        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $tenantA->id)->value('users_count')
        );

        // End tenant A context and create tenant B
        $this->forgetTenant();

        $tenantB = $this->createTestTenant();
        $this->createdDatabases[] = $tenantB->database()->getName();
        $this->initializeTenant($tenantB);

        // Create a user in tenant B
        User::factory()->create();

        // Each tenant should have exactly 1 user
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

        // Two separate rows exist
        $this->assertEquals(2, TenantResourceUsage::count());
    }

    public function test_deleting_user_in_tenant_b_does_not_affect_tenant_a_counts(): void
    {
        // Create a user in tenant A
        User::factory()->create();
        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('users_count')
        );

        // End tenant A context and create tenant B
        $this->forgetTenant();

        $tenantB = $this->createTestTenant();
        $this->createdDatabases[] = $tenantB->database()->getName();
        $this->initializeTenant($tenantB);

        // Create and delete a user in tenant B
        $userB = User::factory()->create();
        $userB->delete();

        // Tenant A's count should be unaffected
        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('users_count'),
            'Tenant A count should remain 1 after delete in tenant B'
        );

        // Tenant B's count should be 0
        $this->assertEquals(
            0,
            TenantResourceUsage::where('tenant_id', $tenantB->id)->value('users_count'),
            'Tenant B count should be 0'
        );
    }

    // -----------------------------------------------------------------------
    //  Observer registration
    // -----------------------------------------------------------------------

    public function test_user_observer_is_registered(): void
    {
        $this->assertTrue(
            User::getEventDispatcher()->hasListeners('eloquent.created: '.User::class),
            'User model should have at least one listener for the created event'
        );

        // Behavioral verification: creating a user updates the count
        User::factory()->create();

        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('users_count')
        );
    }

    // -----------------------------------------------------------------------
    //  Rapid successive operations
    // -----------------------------------------------------------------------

    public function test_rapid_successive_creates_and_deletes_produce_correct_count(): void
    {
        $this->forgetTenant();

        // Re-initialize with a clean count
        $this->initializeTenant($this->tenant);

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $u1->delete();
        $u3 = User::factory()->create();
        $u2->delete();

        // u3 is the only remaining user
        $this->assertEquals(
            1,
            TenantResourceUsage::where('tenant_id', $this->tenant->id)->value('users_count')
        );
    }
}
