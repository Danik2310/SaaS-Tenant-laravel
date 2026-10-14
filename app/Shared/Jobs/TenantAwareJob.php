<?php

declare(strict_types=1);

namespace App\Shared\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for jobs that must run inside a tenant's context.
 *
 * Flow (`handle()`):
 *  1. `tenancy()->initialize($tenant)` switches the database, cache, filesystem
 *     and queue to the tenant.
 *  2. Run the concrete {@see execute()} implementation.
 *  3. Always `tenancy()->end()` in a `finally`, keyed on the package's
 *     `initialized` flag so a bootstrapper failure cannot leak tenant context
 *     into the next job on a long-lived worker.
 */
abstract class TenantAwareJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle(): void
    {
        try {
            tenancy()->initialize($this->tenant);

            $this->execute();
        } finally {
            // Key cleanup on the package flag, not a local one: tenancy marks
            // itself initialized *before* bootstrappers run, so a bootstrapper
            // failure would otherwise skip end() and leak tenant context into
            // the next job on a long-lived worker.
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    /** Tenant-scoped work to run inside {@see handle()}'s tenancy context. */
    abstract protected function execute(): void;
}
