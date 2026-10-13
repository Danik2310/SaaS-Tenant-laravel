<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Tenants\Contracts\TenantManagerInterface;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class TenantProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected array $createdTenantDbNames = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        foreach ($this->createdTenantDbNames as $name) {
            try {
                DB::statement("DROP DATABASE IF EXISTS `$name`");
            } catch (\Exception $e) {
                // already dropped
            }
        }

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function provisionData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Acme Corp',
            'email' => 'owner@acme.test',
            'domain' => 'acme-corp.sasapp',
            'plan' => 'trial',
            'company_name' => 'Acme Corp',
            'public_signup' => true,
        ], $overrides);
    }

    public function test_provision_uses_a_fresh_builder_for_each_tenant(): void
    {
        // Resolve the singleton manager once: the builder it uses must not leak
        // one tenant into the next provision() call.
        $manager = app(TenantManagerInterface::class);

        $first = $manager->provision($this->provisionData([
            'email' => 'first@acme.test',
            'domain' => 'first.sasapp',
        ]));

        $second = $manager->provision($this->provisionData([
            'email' => 'second@acme.test',
            'domain' => 'second.sasapp',
        ]));

        $this->createdTenantDbNames[] = $first->database()->getName();
        $this->createdTenantDbNames[] = $second->database()->getName();

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('first.sasapp', $first->domains()->first()->domain);
        $this->assertSame('second.sasapp', $second->domains()->first()->domain);
    }

    public function test_invalid_plan_is_rejected_before_any_tenant_is_created(): void
    {
        $this->expectException(InvalidArgumentException::class);

        try {
            app(TenantManagerInterface::class)->provision($this->provisionData([
                'plan' => 'does-not-exist',
            ]));
        } finally {
            $this->assertSame(0, Tenant::count());
        }
    }

    public function test_duplicate_domain_is_rejected_before_any_tenant_is_created(): void
    {
        Tenant::withoutEvents(fn () => Tenant::create([
            'id' => 'TEN-000100',
            'name' => 'Existing Workspace',
            'email' => 'existing@acme.test',
            'status' => 'Active',
        ]));

        DB::table('domains')->insert([
            'tenant_id' => 'TEN-000100',
            'domain' => 'taken.sasapp',
            'is_primary' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);

        try {
            app(TenantManagerInterface::class)->provision($this->provisionData([
                'email' => 'new@acme.test',
                'domain' => 'taken.sasapp',
            ]));
        } finally {
            // Only the pre-existing tenant remains — no orphaned row.
            $this->assertSame(1, Tenant::count());
        }
    }
}
