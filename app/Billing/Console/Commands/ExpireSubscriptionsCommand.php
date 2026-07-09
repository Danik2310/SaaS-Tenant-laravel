<?php

declare(strict_types=1);

namespace App\Billing\Console\Commands;

use App\Models\Subscription;
use App\Tenants\States\TenantStateManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'tenants:expire-subscriptions
        {--dry-run : List subscriptions that would be affected without making changes}';

    protected $description = 'Expire active subscriptions past their end date and suspend tenants';

    public function handle(): int
    {
        $expired = Subscription::with('tenant')
            ->expired()
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired subscriptions found.');

            return self::SUCCESS;
        }

        $this->info("Found {$expired->count()} expired subscription(s).");

        foreach ($expired as $subscription) {
            $tenant = $subscription->tenant;

            if ($this->option('dry-run')) {
                $this->line("[DRY-RUN] Would expire subscription {$subscription->id} for tenant {$tenant->id} ({$tenant->name})");

                continue;
            }

            try {
                $subscription->update(['status' => 'expired']);

                TenantStateManager::transitionTo($tenant, 'Suspended');

                Log::info('Tenant suspended due to subscription expiration', [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'subscription_id' => $subscription->id,
                    'ends_at' => $subscription->ends_at,
                ]);

                $this->line("Expired subscription {$subscription->id} and suspended tenant {$tenant->id} ({$tenant->name})");
            } catch (\Exception $e) {
                $this->error("Failed to expire subscription {$subscription->id} for tenant {$tenant->id}: {$e->getMessage()}");
                Log::error('Subscription expiration failed', [
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
