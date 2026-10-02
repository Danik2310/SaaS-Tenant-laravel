import { LIMIT_ROWS, formatAmount, formatPeriod, isFree, isRecommended, limitValue } from './planFormat';

export default function PlanSummary({ plan, variant = 'rail' }) {
    if (!plan) {
        return null;
    }

    const body = (
        <>
            <div className="flex items-baseline justify-between gap-3">
                <p
                    data-testid={variant === 'rail' ? 'plan-summary-name' : 'plan-summary-name-bar'}
                    className="text-sm font-bold uppercase tracking-[0.14em] text-gray-900"
                >
                    {plan.name}
                </p>

                {isRecommended(plan) && (
                    <span className="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-brand-700">
                        Most popular
                    </span>
                )}
            </div>

            <p className="mt-3 font-display text-3xl leading-none text-ink">{formatAmount(plan)}</p>

            {formatPeriod(plan) && (
                <p className="mt-1 text-xs text-gray-500">{formatPeriod(plan)}</p>
            )}

            <dl className="mt-4 space-y-2 border-t border-gray-100 pt-4 text-sm">
                {LIMIT_ROWS.map((row) => (
                    <div key={row.key} className="flex items-baseline justify-between gap-2">
                        <dt className="text-gray-500">{row.label}</dt>
                        <dd className="font-medium tabular-nums text-gray-800">
                            {limitValue(plan.limits?.[row.key])}
                        </dd>
                    </div>
                ))}
            </dl>

            <p className="mt-4 text-xs text-gray-500">
                {plan.slug === 'trial'
                    ? '14-day free trial. No credit card required.'
                    : isFree(plan)
                      ? 'No charge. You can upgrade later.'
                      : 'You will be taken to a secure checkout to finish.'}
            </p>
        </>
    );

    if (variant === 'bar') {
        return (
            <div
                data-testid={variant === 'rail' ? 'plan-summary' : 'plan-summary-bar'}
                data-variant="bar"
                className="mb-6 rounded-xl border border-brand-100 bg-brand-50/60 p-4 lg:hidden"
            >
                {body}
            </div>
        );
    }

    return (
        <aside
            data-testid={variant === 'rail' ? 'plan-summary' : 'plan-summary-bar'}
            data-variant="rail"
            aria-label="Your selected plan"
            className="hidden self-start rounded-2xl border border-gray-200 bg-white p-6 lg:sticky lg:top-8 lg:block"
        >
            {body}
        </aside>
    );
}