<?php

declare(strict_types=1);

namespace App\Commands\Domain;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class SyncTenantSubscriptionsCommand
{
    private array $createdSubscriptionIds = [];

    public function execute(?string $tenantId = null): int
    {
        $query = Tenant::query()->whereNotNull('plan_id');

        if ($tenantId) {
            $query->where('id', $tenantId);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            return 0;
        }

        $created = 0;

        foreach ($tenants as $tenant) {
            if ($tenant->activeSubscription) {
                continue;
            }

            $plan = $tenant->plan;

            if (! $plan) {
                Log::warning('SyncTenantSubscriptionsCommand: tenant has plan_id but plan not found', [
                    'tenant_id' => $tenant->id,
                ]);

                continue;
            }

            $subscription = Subscription::createForTenant($tenant, $plan, 'active');
            $this->createdSubscriptionIds[] = $subscription->id;
            $created++;
        }

        return $created;
    }

    public function undo(): int
    {
        $count = Subscription::whereIn('id', $this->createdSubscriptionIds)->delete();
        $this->createdSubscriptionIds = [];

        return $count;
    }
}
