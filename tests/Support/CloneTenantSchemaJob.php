<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

/**
 * Drop-in replacement for stancl's MigrateDatabase job inside the TEST-only
 * provisioning pipeline (see Tests\CreatesApplication). Clones the
 * pre-migrated tenant schema template instead of running every tenant
 * migration, cutting per-tenant provisioning from seconds to a handful of
 * fast DDL statements. Production keeps the real MigrateDatabase job.
 *
 * @see TenantSchemaCloner
 */
class CloneTenantSchemaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var TenantWithDatabase */
    protected $tenant;

    public function __construct(TenantWithDatabase $tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle(): void
    {
        TenantSchemaCloner::cloneTo($this->tenant->database()->getName());
    }
}
