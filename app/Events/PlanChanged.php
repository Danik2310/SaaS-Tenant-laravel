<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;

class PlanChanged
{
    use Dispatchable;

    public function __construct(
        public Tenant $tenant,
        public Plan $oldPlan,
        public Plan $newPlan,
    ) {}
}
