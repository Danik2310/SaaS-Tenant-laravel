export const RECOMMENDED_SLUG = 'growth';

export const MAX_VISIBLE_FEATURES = 4;

export const LIMIT_ROWS = [
    { key: 'users', label: 'Users' },
    { key: 'warehouses', label: 'Warehouses' },
    { key: 'products', label: 'Products' },
    { key: 'categories', label: 'Categories' },
    { key: 'storage', label: 'Storage (MB)' },
];

export function formatAmount(plan) {
    const amount = Number(plan.price);

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: plan.currency || 'USD',
    }).format(amount);
}

export function formatPeriod(plan) {
    if (Number(plan.price) === 0) {
        return null;
    }

    if (!plan.duration_months || plan.duration_months === 1) {
        return 'per month';
    }

    if (plan.duration_months === 12) {
        return 'per year';
    }

    return `every ${plan.duration_months} months`;
}

export function formatPrice(plan) {
    if (Number(plan.price) === 0) {
        return 'Free';
    }

    return `${formatAmount(plan)}/${plan.duration_months === 12 ? 'yr' : 'mo'}`;
}

export function isFree(plan) {
    return Number(plan.price) === 0;
}

export function isRecommended(plan) {
    return plan.slug === RECOMMENDED_SLUG;
}

export function featureLabel(featureDefinitions, key) {
    return featureDefinitions?.[key]?.label ?? key;
}

export function visibleFeatures(plan) {
    return (plan.features ?? []).slice(0, MAX_VISIBLE_FEATURES);
}

export function hiddenFeatureCount(plan) {
    return Math.max(0, (plan.features ?? []).length - MAX_VISIBLE_FEATURES);
}

export function limitValue(value) {
    return value === null || value === undefined ? 'Unlimited' : String(value);
}

/** The card CTA always starts with "Sign up" so it reads as one action family. */
export function ctaLabel(plan) {
    if (plan.slug === 'trial') {
        return 'Sign up with Trial';
    }

    if (isFree(plan)) {
        return `Sign up free`;
    }

    return `Sign up — ${formatAmount(plan)} at checkout`;
}