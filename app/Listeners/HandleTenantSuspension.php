<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TenantSuspended;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HandleTenantSuspension
{
    public function handle(TenantSuspended $event): void
    {
        try {
            tenancy()->initialize($event->tenant);
            Cache::tags(['tenant_'.$event->tenant->id])->flush();
            tenancy()->end();
        } catch (\Throwable $e) {
            Log::warning('Could not flush cache for tenant '.$event->tenant->id.' after suspension: '.$e->getMessage());
        }
    }
}
