<?php

declare(strict_types=1);

namespace App\Shared\Console\Commands;

use App\Models\Tenant;
use App\Shared\Jobs\ScanTenantNotifications;
use Illuminate\Console\Command;

/**
 * `notifications:scan-triggers` — sweeps tenants for alert conditions.
 *
 * Dispatches one tenant-aware job per active tenant (or the selected IDs), so
 * no tenant context leaks between iterations. Safe to re-run: the dispatcher's
 * dedupe keys make repeat scans no-ops.
 */
class ScanNotificationTriggersCommand extends Command
{
    protected $signature = 'notifications:scan-triggers {--tenant=* : Specific tenant IDs to scan}';

    protected $description = 'Scan tenants for passwordless users and low stock';

    public function handle(): int
    {
        $tenantIds = $this->option('tenant');

        $query = ! empty($tenantIds)
            ? Tenant::whereIn('id', $tenantIds)
            : Tenant::where('status', 'Active');

        $count = (clone $query)->count();

        $this->info("Scanning notifications for {$count} tenant(s)...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->lazyById(200)->each(function (Tenant $tenant) use ($bar) {
            ScanTenantNotifications::dispatch($tenant);
            $bar->advance();
        });

        $bar->finish();
        $this->newLine();
        $this->info('Notification scan jobs dispatched.');

        return Command::SUCCESS;
    }
}
