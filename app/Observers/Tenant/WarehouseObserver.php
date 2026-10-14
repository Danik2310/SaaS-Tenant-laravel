<?php

declare(strict_types=1);

namespace App\Observers\Tenant;

use App\Models\TenantResourceUsage;
use App\Models\Warehouse;

/** Keeps `warehouses_count` in sync on create/delete/restore. */
class WarehouseObserver
{
    public function created(Warehouse $warehouse): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'warehouses_count', 1);
    }

    public function deleted(Warehouse $warehouse): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'warehouses_count', -1);
    }

    public function restored(Warehouse $warehouse): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'warehouses_count', 1);
    }
}
