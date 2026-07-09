<?php

declare(strict_types=1);

namespace App\Tenants\Listeners;

use App\Tenants\Events\TenantSuspended;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HandleTenantSuspension
{
    public function handle(TenantSuspended $event): void
    {
        try {
            Cache::tags(['tenant_'.$event->tenant->getTenantKey()])->flush();
        } catch (\BadMethodCallException) {
            // Cache driver does not support tags (array/file)
        } catch (\Throwable $e) {
            Log::warning('Could not flush cache for tenant '.$event->tenant->id.' after suspension: '.$e->getMessage());
        }
    }
}
