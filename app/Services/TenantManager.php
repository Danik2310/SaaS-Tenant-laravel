<?php

declare(strict_types=1);

namespace App\Services;

use App\Builders\TenantBuilder;
use App\Contracts\TenantManagerInterface;
use App\Events\PlanChanged;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class TenantManager implements TenantManagerInterface
{
    public function provision(array $data): Tenant
    {
        return (new TenantBuilder($data))
            ->withDomain($data['domain'])
            ->withPlan($data['plan'] ?? null)
            ->withDatabase()
            ->withMigrations()
            ->withSeed()
            ->build();
    }

    public function suspend(Tenant $tenant): void
    {
        $tenant->status = 'Suspended';
        $tenant->save();

        Cache::tags(['tenant_'.$tenant->id])->flush();
    }

    public function delete(Tenant $tenant): void
    {
        $tenant->status = 'Deleted';
        $tenant->save();
        $tenant->delete();
    }

    public function restore(Tenant $tenant): void
    {
        $tenant->status = 'Active';
        $tenant->deleted_at = null;
        $tenant->save();
    }

    public function changePlan(Tenant $tenant, Plan $newPlan): void
    {
        $oldPlan = $tenant->plan ?? Plan::where('slug', 'free')->firstOrNew([]);

        $tenant->plan_id = $newPlan->id;
        $tenant->save();

        Cache::tags(['tenant_'.$tenant->id])->flush();

        event(new PlanChanged($tenant, $oldPlan, $newPlan));
    }
}
