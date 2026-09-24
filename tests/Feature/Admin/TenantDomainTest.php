<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class TenantDomainTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    public function test_admin_can_attach_secondary_domain(): void
    {
        $this->setUpAdminAuth();

        $tenant = Tenant::factory()->create();
        $originalDomain = $tenant->domains()->first()->domain;
        $domainColumnBefore = $tenant->fresh()->domain;

        $response = $this->postJson("/admin/api/tenants/{$tenant->id}/domains", [
            'domain' => 'acme-backup.sasapp',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Domain added successfully')
            ->assertJsonCount(2, 'tenant.all_domains');

        $this->assertDatabaseHas('domains', [
            'tenant_id' => $tenant->id,
            'domain' => 'acme-backup.sasapp',
            'is_primary' => 0,
        ]);

        $this->assertCount(2, $tenant->fresh()->domains);
        $this->assertSame($domainColumnBefore, $tenant->fresh()->domain);
        $this->assertSame($originalDomain, $tenant->fresh()->domains->first()->domain);
    }

    public function test_secondary_domain_is_attached_lowercased(): void
    {
        $this->setUpAdminAuth();

        $tenant = Tenant::factory()->create();

        $this->postJson("/admin/api/tenants/{$tenant->id}/domains", [
            'domain' => 'AcMe-BaCkUp.SasApp',
        ])->assertStatus(201);

        $this->assertDatabaseHas('domains', [
            'tenant_id' => $tenant->id,
            'domain' => 'acme-backup.sasapp',
        ]);
    }

    public function test_attaching_domain_in_use_by_another_tenant_is_rejected(): void
    {
        $this->setUpAdminAuth();

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $domainOfB = $tenantB->domains()->first()->domain;

        $this->postJson("/admin/api/tenants/{$tenantA->id}/domains", [
            'domain' => $domainOfB,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('domain');

        $this->assertCount(1, $tenantA->fresh()->domains);
    }

    public function test_admin_can_detach_secondary_domain(): void
    {
        $this->setUpAdminAuth();

        $tenant = Tenant::factory()->create();
        $domainId = $tenant->domains()->create([
            'domain' => 'acme-backup.sasapp',
            'is_primary' => false,
        ])->id;

        $response = $this->deleteJson("/admin/api/tenants/{$tenant->id}/domains/{$domainId}");

        $response->assertOk()
            ->assertJsonPath('message', 'Domain removed successfully')
            ->assertJsonCount(1, 'tenant.all_domains');

        $this->assertDatabaseMissing('domains', ['id' => $domainId]);
        $this->assertCount(1, $tenant->fresh()->domains);
    }

    public function test_primary_domain_cannot_be_detached(): void
    {
        $this->setUpAdminAuth();

        $tenant = Tenant::factory()->create();
        $primaryDomainId = $tenant->domains()->first()->id;

        $this->deleteJson("/admin/api/tenants/{$tenant->id}/domains/{$primaryDomainId}")
            ->assertStatus(422);

        $this->assertDatabaseHas('domains', ['id' => $primaryDomainId]);
        $this->assertCount(1, $tenant->fresh()->domains);
    }

    public function test_last_remaining_domain_cannot_be_detached(): void
    {
        $this->setUpAdminAuth();

        $tenant = Tenant::factory()->create();
        $domainId = $tenant->domains()->first()->id;

        $this->deleteJson("/admin/api/tenants/{$tenant->id}/domains/{$domainId}")
            ->assertStatus(422);

        $this->assertDatabaseHas('domains', ['id' => $domainId]);
    }

    public function test_domain_mutations_require_edit_tenants_permission(): void
    {
        $this->setUpAdminAuth();
        $this->actingAsWithPermissions(['view tenants']);

        $tenant = Tenant::factory()->create();
        $domainId = $tenant->domains()->first()->id;

        $this->postJson("/admin/api/tenants/{$tenant->id}/domains", [
            'domain' => 'acme-backup.sasapp',
        ])->assertForbidden();

        $this->deleteJson("/admin/api/tenants/{$tenant->id}/domains/{$domainId}")
            ->assertForbidden();
    }

    private function actingAsWithPermissions(array $permissionNames): AdminUser
    {
        $role = Role::create(['name' => 'viewer-'.uniqid(), 'guard_name' => 'admin', 'is_active' => true]);
        $role->syncPermissions($permissionNames);

        $admin = AdminUser::factory()->create();
        $admin->assignRole($role);
        $this->actingAs($admin, 'admin');

        return $admin;
    }
}
