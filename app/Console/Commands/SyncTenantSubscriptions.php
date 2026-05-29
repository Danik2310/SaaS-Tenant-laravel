<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Commands\Domain\SyncTenantSubscriptionsCommand;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SyncTenantSubscriptions extends Command
{
    protected $signature = 'tenants:sync-subscriptions
                            {--dry-run : Show what would be created without making changes}
                            {--tenant= : Sync only a specific tenant by ID}';

    protected $description = 'Create active subscriptions for tenants that have a plan but no subscription record';

    public function handle(): int
    {
        $query = Tenant::query()->whereNotNull('plan_id');

        if ($tenantId = $this->option('tenant')) {
            $query->where('id', $tenantId);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found with a plan assigned.');

            return self::SUCCESS;
        }

        $this->info("Found {$tenants->count()} tenant(s) with a plan assigned.");

        $created = 0;
        $skipped = 0;

        foreach ($tenants as $tenant) {
            if ($tenant->activeSubscription) {
                $skipped++;

                continue;
            }

            $plan = $tenant->plan;

            if (! $plan) {
                $this->warn("Tenant [{$tenant->id}] has plan_id but plan not found. Skipping.");
                $skipped++;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("Would create subscription for tenant [{$tenant->id}] ({$tenant->name}) on plan [{$plan->slug}].");
                $created++;

                continue;
            }

            $command = new SyncTenantSubscriptionsCommand;
            $command->execute($tenant->id);

            $this->line("Created subscription for tenant [{$tenant->id}] ({$tenant->name}) on plan [{$plan->slug}].");
            $created++;
        }

        $this->info("Done. Created: {$created}, Skipped (already has subscription): {$skipped}.");

        return self::SUCCESS;
    }
}
