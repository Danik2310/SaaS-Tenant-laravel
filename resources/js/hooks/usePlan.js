import { usePage } from '@inertiajs/react';

export function usePlan() {
  const { plan } = usePage().props;

  return {
    plan,
    hasPlan: !!plan,
    hasFeature(feature) {
      if (!plan) return false;
      if (!Array.isArray(plan.features)) return false;
      return plan.features.includes(feature) || plan.features[feature] === true;
    },
    getLimit(resource) {
      if (!plan || !plan.limits) return 0;
      return plan.limits[resource] ?? 0;
    },
    isUnlimited(resource) {
      const limit = this.getLimit(resource);
      return limit >= 2147483647;
    },
    isOnTrial() {
      return plan?.is_on_trial ?? false;
    },
    trialHasExpired() {
      return plan?.trial_has_expired ?? false;
    },
    isPlan(slug) {
      if (!plan) return false;
      return plan.slug === slug;
    },
    isAtLeast(slug) {
      const tiers = ['free', 'pro', 'enterprise'];
      if (!plan) return false;
      const planIndex = tiers.indexOf(plan.slug);
      const targetIndex = tiers.indexOf(slug);
      return planIndex >= targetIndex;
    },
  };
}

export function useFeature(feature) {
  return usePlan().hasFeature(feature);
}

export function useLimit(resource) {
  return usePlan().getLimit(resource);
}
