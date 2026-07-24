<?php

declare(strict_types=1);

namespace App\Tenants\Builders;

use App\Models\Domain;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Tenants\Contracts\TenantBuilderInterface;
use InvalidArgumentException;

class TenantBuilder implements TenantBuilderInterface
{
    private Tenant $tenant;

    public function __construct()
    {
        $this->tenant = new Tenant;
    }

    public function for(Tenant $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function withData(array $data): static
    {
        $status = $data['status'] ?? (isset($data['trial_ends_at']) ? 'Trial' : 'Active');

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => $status,
            'trial_ends_at' => $data['trial_ends_at'] ?? null,
        ];

        if (isset($data['id'])) {
            $attributes['id'] = $data['id'];
        }

        $this->tenant = Tenant::create($attributes);

        return $this;
    }

    public function withDomain(string $domain): static
    {
        $existing = Domain::where('domain', $domain)->exists();

        if ($existing) {
            throw new InvalidArgumentException("Domain '{$domain}' is already in use by another tenant.");
        }

        $this->tenant->domains()->create(['domain' => $domain]);

        return $this;
    }

    public function withPlan(?string $planSlug = null): static
    {
        if ($planSlug) {
            $plan = Plan::where('slug', $planSlug)->first();
            if (! $plan) {
                throw new InvalidArgumentException(
                    "Plan '{$planSlug}' not found. Available plans: "
                    .Plan::pluck('slug')->implode(', ')
                );
            }

            if ($plan->max_users === 0) {
                throw new InvalidArgumentException("Plan '{$plan->name}' does not support any users.");
            }

            $this->tenant->plan_id = $plan->id;
            $this->tenant->save();

            if (! $this->tenant->activeSubscription) {
                $endsAt = $this->tenant->trial_ends_at;
                Subscription::createForTenant($this->tenant, $plan, 'active', $endsAt);
            }
        }

        return $this;
    }

    public function build(): Tenant
    {
        if (! $this->tenant->exists) {
            throw new InvalidArgumentException(
                'TenantBuilder::build() failed: withData() must be called before build(). '
                .'Ensure tenant data is provided before building.'
            );
        }

        if (empty($this->tenant->id)) {
            throw new InvalidArgumentException(
                'TenantBuilder::build() failed: tenant has no ID. '
                .'The tenant was not properly persisted.'
            );
        }

        return $this->tenant->fresh();
    }
}
