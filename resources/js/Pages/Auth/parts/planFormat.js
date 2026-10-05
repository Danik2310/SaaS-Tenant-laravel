export const RECOMMENDED_SLUG = 'growth';

export const MAX_VISIBLE_FEATURES = 4;

export const LIMIT_ROWS = [
    { key: 'users',      label: 'Users' },
    { key: 'warehouses', label: 'Warehouses' },
    { key: 'products',   label: 'Products' },
    { key: 'categories', label: 'Categories' },
    { key: 'storage',    label: 'Storage', unit: 'mb' },
];

function money(amount, currency) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency || 'USD',
        minimumFractionDigits: Number.isInteger(amount) ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

export function formatAmount(plan) {
    return money(Number(plan.price), plan.currency);
}

export function isFree(plan) {
    return Number(plan.price) === 0;
}

export function isTrial(plan) {
    return plan.slug === 'trial';
}

export function isRecommended(plan) {
    return plan.slug === RECOMMENDED_SLUG;
}

export function periodLabel(plan, trialDays = 14) {
    if (isFree(plan)) {
        return isTrial(plan) ? `for ${trialDays} days` : 'forever';
    }
    if (!plan.duration_months || plan.duration_months === 1) return 'per month';
    if (plan.duration_months === 12) return 'per year';

    return `every ${plan.duration_months} months`;
}

export function cadenceNote(plan) {
    const months = Number(plan.duration_months);
    if (isFree(plan) || !months || months <= 1) return null;

    return `about ${money(Number(plan.price) / months, plan.currency)} a month, billed ${
        months === 12 ? 'yearly' : `every ${months} months`
    }`;
}

export function featureLabel(featureDefinitions, key) {
    return featureDefinitions?.[key]?.label ?? key;
}

export function orderedFeatures(plan, featureDefinitions) {
    return (plan.features ?? [])
        .map((key, index) => ({ key, index }))
        .sort((a, b) => {
            const sa = featureDefinitions?.[a.key]?.sort_order;
            const sb = featureDefinitions?.[b.key]?.sort_order;

            if (sa === sb) return a.index - b.index;
            if (sa === undefined) return 1;
            if (sb === undefined) return -1;

            return sa - sb;
        })
        .map(({ key }) => key);
}

export function limitValue(value, unit) {
    if (value === null || value === undefined) return 'Unlimited';

    if (unit === 'mb') {
        const mb = Number(value);
        if (mb >= 1024) {
            const gb = mb / 1024;
            return `${Number(gb.toFixed(gb % 1 === 0 ? 0 : 1))} GB`;
        }
        return `${mb} MB`;
    }

    return String(value);
}

export function planFootnote(plan) {
    if (plan.footnote) return plan.footnote;
    if (isTrial(plan)) return '14-day free trial. No credit card required.';
    if (isFree(plan)) return 'No charge. You can upgrade later.';

    return 'You will be taken to a secure checkout to finish.';
}

export function ctaLabel(plan) {
    if (isTrial(plan)) return 'Sign up with Trial';
    if (isFree(plan)) return 'Sign up free';

    return `Sign up with ${plan.name}`;
}
