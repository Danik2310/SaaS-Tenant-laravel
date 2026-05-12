<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TenantReactivated;

class HandleTenantReactivation
{
    public function handle(TenantReactivated $event): void
    {
        Cache::tags(['tenant_'.$event->tenant->id])->flush();
    }
}
