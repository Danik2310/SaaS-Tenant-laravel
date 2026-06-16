<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantForceDeleteWithEventTest extends TestCase
{
    use RefreshDatabase;

    protected array $createdTenantDbNames = [];

    public function test_tenant_force_delete_triggers_tenant_deleted_event_and_drops_database(): void
    {
        $plan = Plan::factory()->create();
        $tenant = $this->createTestTenant();
        $tenant->update(['plan_id' => $plan->id]);
        $dbName = $tenant->database()->getName();
        $this->createdTenantDbNames[] = $dbName;

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);

        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        $permission = Permission::firstOrCreate(['name' => 'manage tenants', 'guard_name' => 'admin']);
        $role->givePermissionTo($permission);
        $user = AdminUser::factory()->create();
        $user->assignRole('super-admin');
        $this->actingAs($user, 'admin');

        $this->delete("/admin/api/tenants/{$tenant->id}");
        $tenant->refresh();
        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);

        $tenant->forceDelete();
        $this->assertModelMissing($tenant);
    }

    public function test_tenant_force_delete_cascades_to_subscriptions(): void
    {
        $plan = Plan::factory()->create();
        $tenant = $this->createTestTenant();
        $tenant->update(['plan_id' => $plan->id]);
        $this->createdTenantDbNames[] = $tenant->database()->getName();

        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
        ]);

        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        $permission = Permission::firstOrCreate(['name' => 'manage tenants', 'guard_name' => 'admin']);
        $role->givePermissionTo($permission);
        $user = AdminUser::factory()->create();
        $user->assignRole('super-admin');
        $this->actingAs($user, 'admin');

        $this->delete("/admin/api/tenants/{$tenant->id}");
        $tenant->refresh();
        $tenant->forceDelete();

        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdTenantDbNames as $name) {
            try {
                DB::statement("DROP DATABASE IF EXISTS `$name`");
            } catch (\Exception $e) {

            }
        }

        parent::tearDown();
    }
}
