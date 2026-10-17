<?php

declare(strict_types=1);

namespace App\Tenants\Builders;

use App\Models\Domain;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Tenants\Contracts\TenantBuilderInterface;
use App\Tenants\TenantManager;
use InvalidArgumentException;

/**
 * Fluent builder for tenant provisioning.
 *
 * Stateful by design — bound (not singleton) so each provision call gets a fresh
 * instance. Used directly for admin-driven flows, or through
 * {@see TenantManager::provision()} which layers validation and id
 * allocation on top.
 *
 * Typical call order:
 *   `withData()->for()->withDomain()->withPlan()->build()`.
 */
class TenantBuilder implements TenantBuilderInterface
{
    private Tenant $tenant;

    public function __construct()
    {
        $this->tenant = new Tenant;
    }

    /** Point the builder at an existing tenant instance. */
    public function for(Tenant $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    /**
     * Create the tenant row from an allowlisted attribute set. Never honours a
     * caller-supplied status/id/trial_ends_at — trial state is applied later by
     * {@see withPlan()}.
     *
     * @param  array<string, mixed>  $data
     */
    public function withData(array $data): static
    {
        // Explicit allowlist: the builder never honors caller-provided
        // status/id/trial_ends_at so crafted input cannot privilege-escalate a
        // tenant (status, plan, identity) during provisioning. Trial state is
        // applied later by withPlan().
        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => 'Active',
            'company_name' => $data['company_name'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address_line1' => $data['address_line1'] ?? null,
            'address_line2' => $data['address_line2'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'country' => $data['country'] ?? null,
        ];

        $this->tenant = Tenant::create($attributes);

        return $this;
    }

    /**
     * Attach a domain to the tenant.
     *
     * @throws InvalidArgumentException when the domain is already in use
     */
    public function withDomain(string $domain): static
    {
        $this->assertDomainAvailable($domain);

        $this->tenant->domains()->create(['domain' => $domain]);

        return $this;
    }

    /**
     * Validation-only counterpart to withDomain(), callable before the tenant
     * row exists so a rejected signup never leaves a database behind.
     */
    public function assertDomainAvailable(string $domain): void
    {
        if (Domain::where('domain', $domain)->exists()) {
            throw new InvalidArgumentException("Domain '{$domain}' is already in use by another tenant.");
        }
    }

    /**
     * Assign a plan: enter Trial when the plan is a trial plan, set `plan_id`
     * and create a subscription if the tenant has none.
     *
     * @throws InvalidArgumentException when the plan is not assignable
     */
    public function withPlan(?string $planSlug = null, bool $publicSignup = false, bool $confirmedPayment = false): static
    {
        $plan = $this->resolveAssignablePlan($planSlug, $publicSignup, $confirmedPayment);

        if ($plan) {
            if ($plan->isTrial()) {
                $this->tenant->status = 'Trial';
                $this->tenant->trial_ends_at ??= now()->addDays((int) config('tenancy.trial_days', 14));
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

    /**
     * Resolve (and validate) the plan a signup may be assigned, without
     * mutating the tenant. Returns null for a null slug.
     */
    public function resolveAssignablePlan(?string $planSlug = null, bool $publicSignup = false, bool $confirmedPayment = false): ?Plan
    {
        if (! $planSlug) {
            return null;
        }

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

        // Defense in depth for the public registration path: never assign
        // an inactive or paid plan without a confirmed payment. Admin
        // provisioning (publicSignup = false) and post-payment signup
        // (confirmedPayment = true) may still assign paid plans.
        if ($publicSignup
            && ($plan->status !== 'active'
                || ((float) $plan->price > 0 && ! $confirmedPayment))) {
            throw new InvalidArgumentException(
                "Plan '{$plan->name}' is not available for self-service signup."
            );
        }

        return $plan;
    }

    /**
     * Finalise and return a fresh copy of the built tenant.
     *
     * @throws InvalidArgumentException when withData() was not called or the row
     *                                  was not persisted
     */
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
