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
     * A plan is eligible when it is active AND either a trial or zero-priced.
     * Paid plans are displayed but are never assignable without payment.
     */
    public function isEligibleForPublicSignup(Plan $plan): bool;

    /**
     * Resolve a client-supplied plan slug into a slug that is safe to provision.
     *
     * Returns the requested slug only when it is eligible for public signup;
     * any paid, inactive, unknown, or empty value falls back to 'trial'.
     */
    public function resolveSignupPlanSlug(?string $requested): string;
}
