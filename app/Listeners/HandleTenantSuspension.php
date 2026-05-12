<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TenantSuspended;
use Illuminate\Support\Facades\Cache;

class HandleTenantSuspension
{
    public function handle(TenantSuspended $event): void
    {
        Cache::tags(['tenant_'.$event->tenant->id])->flush();
    }
}
