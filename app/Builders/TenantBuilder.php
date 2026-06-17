<?php

declare(strict_types=1);

namespace App\Builders;

use App\Contracts\TenantBuilderInterface;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use InvalidArgumentException;

class TenantBuilder implements TenantBuilderInterface
{
    private Tenant $tenant;

    public function __construct()
    {
        $this->tenant = new Tenant;
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
        return $this->tenant->fresh();
    }
}
