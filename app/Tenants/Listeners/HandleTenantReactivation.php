<?php

declare(strict_types=1);

namespace App\Tenants\Listeners;

use App\Tenants\Events\TenantReactivated;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HandleTenantReactivation
{
    public function handle(TenantReactivated $event): void
    {
        try {
            Cache::tags(['tenant_'.$event->tenant->getTenantKey()])->flush();
        } catch (\BadMethodCallException) {
            // Cache driver does not support tags (array/file)
        } catch (\Throwable $e) {
            Log::warning('Could not flush cache for tenant '.$event->tenant->id.' after reactivation: '.$e->getMessage());
        }
    }
}
