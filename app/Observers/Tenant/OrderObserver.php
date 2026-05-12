<?php

declare(strict_types=1);

namespace App\Observers\Tenant;

use App\Models\Order;
use App\Models\TenantResourceUsage;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'orders_count', 1);
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'orders_count', -1);
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'orders_count', 1);
    }
}
