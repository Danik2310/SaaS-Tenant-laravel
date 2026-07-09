<?php

declare(strict_types=1);

namespace App\Tenants\Events;

use App\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;

class TenantReactivated
{
    use Dispatchable;

    public function __construct(
        public Tenant $tenant,
    ) {}
}
