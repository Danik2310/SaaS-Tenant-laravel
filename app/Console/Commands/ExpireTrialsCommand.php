<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\States\TenantStateManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireTrialsCommand extends Command
{
    protected $signature = 'tenants:expire-trials
        {--dry-run : List tenants that would be affected without making changes}';

    protected $description = 'Expire trial periods for tenants past their trial_ends_at date';

    public function handle(): int
    {
        $expired = Tenant::query()
            ->where('status', 'Trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired trials found.');

            return self::SUCCESS;
        }

        $this->info("Found {$expired->count()} tenant(s) with expired trials.");

        foreach ($expired as $tenant) {
            if ($this->option('dry-run')) {
                $this->line("[DRY-RUN] Would suspend tenant {$tenant->id} ({$tenant->name}) - trial expired {$tenant->trial_ends_at}");

                continue;
            }

            try {
                TenantStateManager::transitionTo($tenant, 'Suspended');

                Log::info('Tenant suspended due to trial expiration', [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'trial_ended_at' => $tenant->trial_ends_at,
                ]);

                $this->line("Suspended tenant {$tenant->id} ({$tenant->name})");
            } catch (\Exception $e) {
                $this->error("Failed to suspend tenant {$tenant->id}: {$e->getMessage()}");
                Log::error('Trial expiration suspension failed', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
