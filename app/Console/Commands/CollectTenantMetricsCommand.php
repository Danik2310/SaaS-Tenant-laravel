<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CollectTenantMetrics;
use App\Models\Tenant;
use Illuminate\Console\Command;

class CollectTenantMetricsCommand extends Command
{
    protected $signature = 'tenants:collect-metrics {--tenant=* : Specific tenant IDs to collect metrics for}';

    protected $description = 'Collect resource usage metrics for all tenants';

    public function handle(): int
    {
        $tenantIds = $this->option('tenant');

        if (!empty($tenantIds)) {
            $tenants = Tenant::whereIn('id', $tenantIds)->get();
        } else {
            $tenants = Tenant::where('status', 'Active')->get();
        }

        $this->info("Collecting metrics for {$tenants->count()} tenant(s)...");

        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();

        foreach ($tenants as $tenant) {
            CollectTenantMetrics::dispatch($tenant);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Metrics collection jobs dispatched successfully.');

        return Command::SUCCESS;
    }
}
