<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Tenant;
use App\Models\TenantResourceUsage;
use Illuminate\Support\Facades\DB;

trait HasTenant
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

        $tenantIds = [];
        foreach ($this->createdTenants as $t) {
            $tenantIds[] = $t->id;
            try {
                $dbName = $t->database()->getName();
                DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
            } catch (\Exception $e) {
                // silently ignore — DB may already be gone or inaccessible
            }
        }

        if ($tenantIds !== []) {
            TenantResourceUsage::whereIn('tenant_id', $tenantIds)->delete();
            Tenant::whereIn('id', $tenantIds)->delete();
        }

        parent::tearDown();
    }
}
