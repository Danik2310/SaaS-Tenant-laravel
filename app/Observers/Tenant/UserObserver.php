<?php

declare(strict_types=1);

namespace App\Observers\Tenant;

use App\Models\TenantResourceUsage;
use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            // Not in tenant context — do nothing
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'users_count', 1);
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'users_count', -1);
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'users_count', 1);
    }
}
