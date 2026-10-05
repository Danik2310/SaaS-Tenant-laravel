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
    const tone = recommended ? 'featured' : 'plain';
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
                        className={`shrink-0 rounded-sm px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.14em] ${t.badge}`}
                    >
                        Most popular
                    </span>
                )}
            </div>

            <p className={`mt-4 flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5 text-3xl font-bold leading-none tracking-tight tabular-nums ${t.amount}`}>
                {formatAmount(plan)}
                <span className={`text-sm font-medium leading-5 ${t.period}`}>{periodLabel(plan, trialDays)}</span>
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
                className="mb-8 rounded-sm border border-white/35 bg-white/[0.05] p-5 lg:hidden"
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
            className="hidden self-start border-t border-white/35 pt-6 lg:sticky lg:top-24 lg:block"
            >
            {body}
        </aside>
    );
}
