<?php

declare(strict_types=1);

namespace App\Shared\Middleware;

use App\Billing\Factories\ResourceEnforcementFactory;
use App\Models\TenantResourceUsage;
use App\Shared\Exceptions\PlanLimitExceededException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckStorageLimit
{
    public function handle(Request $request, Closure $next, ?string $requiredKb = null): Response
    {
        $tenant = tenant();

        if ($tenant === null) {
            return $next($request);
        }

        $limitMb = app(ResourceEnforcementFactory::class)->make($tenant)->maxStorageMb();

        if ($limitMb === PHP_INT_MAX) {
            return $next($request);
        }

        $limitKb = $limitMb * 1024;

        $usage = TenantResourceUsage::where('tenant_id', $tenant->id)->first();
        $currentKb = $usage ? (int) $usage->storage_kb : 0;

        $neededKb = $requiredKb !== null ? (int) $requiredKb : 1;

        if (($currentKb + $neededKb) > $limitKb) {
            throw new PlanLimitExceededException('storage', $limitMb);
        }

        return $next($request);
    }
}
