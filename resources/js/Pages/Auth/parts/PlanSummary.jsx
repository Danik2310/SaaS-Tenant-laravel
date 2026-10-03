import PlanLimits from './PlanLimits';
import {
    cadenceNote,
    formatAmount,
    isRecommended,
    periodLabel,
    planFootnote,
} from './planFormat';
import { CARD_TONES } from './planTones.js';

export default function PlanSummary({ plan, variant = 'rail', trialDays = 14 }) {
    if (!plan) {
        return null;
    }

    const recommended = isRecommended(plan);
    const tone = recommended ? 'ink' : 'light';
    const t = CARD_TONES[tone];

    const body = (
        <>
            <div className="flex items-baseline justify-between gap-3">
                <p
                    data-testid={variant === 'rail' ? 'plan-summary-name' : 'plan-summary-name-bar'}
                    className={`text-base font-bold uppercase leading-5 tracking-[0.12em] ${t.eyebrow}`}
                >
                    {plan.name}
                </p>

                {recommended && (
                    <span
                        className={`shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.14em] ${t.badge}`}
                    >
                        <span aria-hidden="true" className="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-current align-middle" />
                        Most popular
                    </span>
                )}
            </div>

            <p className={`mt-4 flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5 font-display text-[2rem] leading-none tracking-tight tabular-nums ${t.amount}`}>
                {formatAmount(plan)}
                <span className={`font-sans text-sm font-medium leading-5 ${t.period}`}>
                    {periodLabel(plan, trialDays)}
                </span>
            </p>

            {cadenceNote(plan) && (
                <p className={`mt-1.5 text-xs leading-4 tabular-nums ${t.footnote}`}>{cadenceNote(plan)}</p>
            )}

            {plan.summary && (
                <p className={`mt-3 text-sm leading-relaxed ${t.summary}`}>{plan.summary}</p>
            )}

            <PlanLimits plan={plan} tone={tone} className="mt-5" />

            <p className={`mt-4 text-xs leading-relaxed ${t.footnote}`}>{planFootnote(plan)}</p>
        </>
    );

    if (variant === 'bar') {
        return (
            <div
                data-testid={variant === 'rail' ? 'plan-summary' : 'plan-summary-bar'}
                data-variant="bar"
                className="mb-8 rounded-2xl border border-brand-100 bg-brand-50/60 p-5 lg:hidden"
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
                className="hidden self-start rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-900/[0.07] lg:sticky lg:top-8 lg:block"
            >
            {body}
        </aside>
    );
}
