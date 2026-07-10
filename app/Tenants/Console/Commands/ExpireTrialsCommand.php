<?php

declare(strict_types=1);

namespace App\Tenants\Console\Commands;

use App\Models\Tenant;
use App\Tenants\States\TenantStateManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireTrialsCommand extends Command
{
    protected $signature = 'tenants:expire-trials
        {--dry-run : List tenants that would be affected without making changes}';

    protected $description = 'Expire trial periods for tenants past their trial_ends_at date';

    public function handle(): int
    {
        $count = 0;

        Tenant::query()
            ->where('status', 'Trial')
            ->where(function ($q) {
                $q->where('trial_ends_at', '<', now())
                    ->orWhereNull('trial_ends_at');
            })
            ->lazy()
            ->each(function (Tenant $tenant) use (&$count) {
                $this->line("Processing tenant {$tenant->id} ({$tenant->name}) - trial ended");

                if ($this->option('dry-run')) {
                    $this->line("[DRY-RUN] Would suspend tenant {$tenant->id} ({$tenant->name})");

                    return;
                }

                try {
                    TenantStateManager::transitionTo($tenant, 'Suspended');

                    Log::info('Tenant suspended due to trial expiration', [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'trial_ended_at' => $tenant->trial_ends_at,
                    ]);

                    $this->line("Suspended tenant {$tenant->id} ({$tenant->name})");
                    $count++;
                } catch (\Exception $e) {
                    $this->error("Failed to suspend tenant {$tenant->id}: {$e->getMessage()}");
                    Log::error('Trial expiration suspension failed', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        if ($count === 0) {
            $this->info('No expired trials found.');
        } else {
            $this->info("Suspended {$count} tenant(s) with expired trials.");
        }

        return self::SUCCESS;
    }
}
