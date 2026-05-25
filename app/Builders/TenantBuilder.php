<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\Plan;
use App\Models\Tenant;
use Database\Seeders\TenantUserRolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;

class TenantBuilder
{
    private Tenant $tenant;

    public function __construct(array $data)
    {
        $status = isset($data['trial_ends_at']) ? 'Trial' : 'Active';

        $this->tenant = Tenant::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => $status,
            'trial_ends_at' => $data['trial_ends_at'] ?? null,
        ]);
    }

    public function withDomain(string $domain): static
    {
        $this->tenant->domains()->create(['domain' => $domain]);

        return $this;
    }

    public function withDatabase(): static
    {
        $this->tenant->database()->makeCredentials();
        $this->tenant->database()->manager()->createDatabase($this->tenant);
        $this->tenant->save();

        return $this;
    }

    public function withMigrations(): static
    {
        Artisan::call('tenants:migrate', ['--tenants' => [$this->tenant->id]]);

        return $this;
    }

    public function withPlan(?string $planSlug = null): static
    {
        if ($planSlug) {
            $plan = Plan::where('slug', $planSlug)->first();
            if ($plan) {
                $this->tenant->plan_id = $plan->id;
                $this->tenant->save();
            }
        }

        return $this;
    }

    public function withSeed(?string $seederClass = null): static
    {
        tenancy()->initialize($this->tenant);
        try {
            Artisan::call('db:seed', [
                '--class' => $seederClass ?? TenantUserRolePermissionSeeder::class,
                '--force' => true,
            ]);
        } catch (\Exception $e) {
            \Log::warning('Failed to seed tenant: '.$e->getMessage(), ['tenant_id' => $this->tenant->id]);
        }
        tenancy()->end();

        return $this;
    }

    public function build(): Tenant
    {
        return $this->tenant->fresh();
    }
}
