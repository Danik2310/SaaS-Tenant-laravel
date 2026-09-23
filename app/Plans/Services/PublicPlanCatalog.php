<?php

declare(strict_types=1);

namespace App\Plans\Services;

use App\Models\Plan;
use App\Shared\Contracts\PublicPlanCatalogInterface;
use Illuminate\Database\Eloquent\Collection;

class PublicPlanCatalog implements PublicPlanCatalogInterface
{
    /**
     * Slug used whenever the requested plan cannot be assigned without payment.
     */
    private const FALLBACK_SLUG = 'trial';

    public function allSignupPlans(): array
    {
        $currency = (string) config('currency.base_currency', 'USD');

        return $this->activePlans()
            ->map(fn (Plan $plan) => [
                'slug' => (string) $plan->slug,
                'name' => (string) $plan->name,
                'status' => (string) $plan->status,
                'price' => (string) $plan->price,
                'currency' => $currency,
                'duration_months' => $plan->duration_months !== null ? (int) $plan->duration_months : null,
                'can_signup' => $this->isEligibleForPublicSignup($plan),
                'limits' => [
                    'users' => $plan->max_users !== null ? (int) $plan->max_users : null,
                    'storage' => $plan->max_storage !== null ? (int) $plan->max_storage : null,
                    'warehouses' => $plan->max_warehouses !== null ? (int) $plan->max_warehouses : null,
                    'categories' => $plan->max_categories !== null ? (int) $plan->max_categories : null,
                    'products' => $plan->max_products !== null ? (int) $plan->max_products : null,
                ],
                'features' => $plan->features,
            ])
            ->values()
            ->all();
    }

    public function isEligibleForPublicSignup(Plan $plan): bool
    {
        return $plan->status === 'active'
            && ($plan->isTrial() || (float) $plan->price === 0.0);
    }

    public function resolveSignupPlanSlug(?string $requested): string
    {
        if ($requested === null || $requested === '') {
            return self::FALLBACK_SLUG;
        }

        $plan = $this->activePlans()
            ->first(fn (Plan $candidate) => $candidate->slug === $requested);

        if (! $plan || ! $this->isEligibleForPublicSignup($plan)) {
            return self::FALLBACK_SLUG;
        }

        return (string) $plan->slug;
    }

    /**
     * Active plans only, feature gates eager loaded to avoid N+1 access.
     *
     * @return Collection<int, Plan>
     */
    private function activePlans()
    {
        return Plan::query()
            ->with('featureGates')
            ->where('status', 'active')
            ->orderByRaw('CASE WHEN slug = ? THEN 0 ELSE 1 END', [self::FALLBACK_SLUG])
            ->orderBy('price')
            ->orderBy('name')
            ->get();
    }
}
