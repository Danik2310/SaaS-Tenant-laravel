<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use App\Models\Plan;

interface PublicPlanCatalogInterface
{
    /**
     * Presentable shape of every plan available for public self-service signup.
     *
     * @return array<int, array{
     *     slug: string,
     *     name: string,
     *     status: string,
     *     price: string,
     *     currency: string,
     *     duration_months: int|null,
     *     can_signup: bool,
     *     limits: array{users: int|null, storage: int|null, warehouses: int|null, categories: int|null, products: int|null},
     *     features: array<int, string>
     * }>
     */
    public function allSignupPlans(): array;

    /**
     * Single source of truth for public-signup eligibility.
     *
     * A plan is eligible when it is active. Paid plans are assignable only
     * after a confirmed payment (see TenantBuilder::withPlan()).
     */
    public function isEligibleForPublicSignup(Plan $plan): bool;

    /**
     * Resolve a client-supplied plan slug into a slug that is safe to provision
     * WITHOUT payment.
     *
     * Returns the requested slug only when it is eligible for public signup
     * AND requires no payment; any paid, inactive, unknown, or empty value
     * falls back to 'trial'. Paid-but-active choices are surfaced separately
     * via activePlanBySlug() so the registration flow can branch to checkout.
     */
    public function resolveSignupPlanSlug(?string $requested): string;

    /**
     * Return the active plan matching the requested slug, regardless of price.
     *
     * Used to detect a paid-plan selection that must go through payment before
     * the tenant is provisioned. Returns null for unknown or inactive slugs.
     */
    public function activePlanBySlug(?string $requested): ?Plan;
}
