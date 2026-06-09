<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TenantReactivated;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HandleTenantReactivation
{
    public function handle(TenantReactivated $event): void
    {
        try {
            tenancy()->initialize($event->tenant);
            Cache::tags(['tenant_'.$event->tenant->id])->flush();
            tenancy()->end();
        } catch (\Throwable $e) {
            Log::warning('Could not flush cache for tenant '.$event->tenant->id.' after reactivation: '.$e->getMessage());
        }
    }
}
