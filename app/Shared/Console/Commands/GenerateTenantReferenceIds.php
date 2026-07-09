<?php

declare(strict_types=1);

namespace App\Shared\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class GenerateTenantReferenceIds extends Command
{
    protected $signature = 'tenants:generate-reference-ids
        {--dry-run : Display what would be done without making changes}';

    protected $description = 'Generate reference IDs for tenants that do not have one';

    public function handle(): int
    {
        $tenants = Tenant::withTrashed()
            ->whereNull('reference_id')
            ->orderBy('created_at')
            ->get();

        if ($tenants->isEmpty()) {
            $this->info('All tenants already have reference IDs.');

            return self::SUCCESS;
        }

        $this->info("Found {$tenants->count()} tenant(s) without reference IDs.");

        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();

        $generated = 0;

        foreach ($tenants as $tenant) {
            $refId = Tenant::generateReferenceId();

            if ($this->option('dry-run')) {
                $this->line(" [DRY-RUN] {$tenant->id} → {$refId}");
            } else {
                $tenant->reference_id = $refId;
                $tenant->save();
            }

            $generated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->info("[DRY-RUN] Would generate {$generated} reference ID(s). Run without --dry-run to apply.");
        } else {
            $this->info("Generated {$generated} reference ID(s).");
        }

        return self::SUCCESS;
    }
}
