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
        tenancy()->initialize($event->tenant);

        try {
            Cache::tags(['tenant_'.$event->tenant->id])->flush();
        } catch (\BadMethodCallException $e) {
            Log::warning('Cache driver does not support tags, skipped flush for tenant '.$event->tenant->id);
        }

        tenancy()->end();
    }
}
