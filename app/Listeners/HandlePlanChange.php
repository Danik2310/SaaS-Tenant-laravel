<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PlanChanged;
use Illuminate\Support\Facades\Log;

class HandlePlanChange
{
    public function handle(PlanChanged $event): void
    {
        $oldPrice = $event->oldPlan->price ?? 0;
        $newPrice = $event->newPlan->price ?? 0;
        $direction = $newPrice < $oldPrice ? 'downgrade' : ($newPrice > $oldPrice ? 'upgrade' : 'lateral');

        $lostFeatures = $direction === 'downgrade'
            ? array_diff($event->oldPlan->features ?? [], $event->newPlan->features ?? [])
            : [];

        Log::info('Tenant plan changed', [
            'tenant_id' => $event->tenant->id,
            'old_plan' => $event->oldPlan->slug,
            'new_plan' => $event->newPlan->slug,
            'direction' => $direction,
            'lost_features' => $lostFeatures,
        ]);

        if ($direction === 'downgrade' && ! empty($lostFeatures)) {
            Log::warning('Tenant lost features on downgrade', [
                'tenant_id' => $event->tenant->id,
                'tenant_name' => $event->tenant->name,
                'lost_features' => $lostFeatures,
                'old_plan' => $event->oldPlan->slug,
                'new_plan' => $event->newPlan->slug,
            ]);
        }
    }
}
