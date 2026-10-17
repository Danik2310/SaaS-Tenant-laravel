<?php

declare(strict_types=1);

namespace App\Tenants\Console\Commands;

use App\Models\Tenant;
use App\Tenants\Jobs\CollectTenantMetrics;
use Illuminate\Console\Command;

/**
 * `tenants:collect-metrics` — dispatches resource-usage collection per tenant.
 *
 * Flow: selects the given `--tenant` IDs or all `Active` tenants and dispatches
 * {@see CollectTenantMetrics} for each.
 */
class CollectTenantMetricsCommand extends Command
{
    protected $signature = 'tenants:collect-metrics {--tenant=* : Specific tenant IDs to collect metrics for}';

    protected $description = 'Collect resource usage metrics for all tenants';

    public function handle(): int
    {
        $tenantIds = $this->option('tenant');

        $query = ! empty($tenantIds)
            ? Tenant::whereIn('id', $tenantIds)
            : Tenant::where('status', 'Active');

        // Count first (cheap, indexed), then stream the rows so a large tenant
        // base does not materialise every model at once.
        $count = (clone $query)->count();

        $this->info("Collecting metrics for {$count} tenant(s)...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->lazyById(200)->each(function (Tenant $tenant) use ($bar) {
            CollectTenantMetrics::dispatch($tenant);
            $bar->advance();
        });

        $bar->finish();
        $this->newLine();
        $this->info('Metrics collection jobs dispatched successfully.');

        return Command::SUCCESS;
    }
}
