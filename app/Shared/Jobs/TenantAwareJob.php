<?php

declare(strict_types=1);

namespace App\Shared\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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

    abstract protected function execute(): void;
}
