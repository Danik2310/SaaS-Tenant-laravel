<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PlanChanged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HandlePlanChange
{
    public function handle(PlanChanged $event): void
    {
        Cache::tags(['tenant_' . $event->tenant->id])->flush();

        Log::info('Tenant plan changed', [
            'tenant_id' => $event->tenant->id,
            'old_plan' => $event->oldPlan->slug,
            'new_plan' => $event->newPlan->slug,
        ]);
    }
}
