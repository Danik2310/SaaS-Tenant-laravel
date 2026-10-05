import PrimaryButton from '@/Components/PrimaryButton';
import PlanFeatures from './PlanFeatures';
import PlanLimits from './PlanLimits';
import { CARD_TONES } from './planTones.js';
import { cadenceNote, ctaLabel, formatAmount, isRecommended, periodLabel } from './planFormat';

export default function PlanCard({
    plan,
    featureDefinitions,
    trialDays = 14,
    onSelect,
    processing,
    index = 0,
}) {
    const recommended = isRecommended(plan);
    const tone = recommended ? 'featured' : 'plain';
    const t = CARD_TONES[tone];
    const cadence = cadenceNote(plan);
    const titleId = `plan-card-${plan.slug}-title`;

    // The spine is the page's hover device instead of an elevation lift: it draws
    // down the leading edge and costs no shadow bloom. Gated to fine pointers in
    // CSS, so touch never latches it after a tap.
    const cardClasses = `sl-plan-card sl-spine animate-sli-fade-in rounded-sm p-5 sm:p-6 relative flex h-full flex-col ${
        recommended ? t.surface : `${t.surface} ${t.hoverShadow}`
    }`;

    return (
        <article
            data-testid={`plan-card-${plan.slug}`}
            data-recommended={recommended ? 'true' : 'false'}
            aria-labelledby={titleId}
            style={index ? { animationDelay: `${Math.min(index, 5) * 80}ms` } : undefined}
            className={cardClasses}
        >
            <div className="flex items-start justify-between gap-3">
                <h3
                    id={titleId}
                    className={`min-w-0 truncate text-base font-bold uppercase leading-5 tracking-[0.12em] ${t.eyebrow}`}
                >
                    {plan.name}
                </h3>

                {recommended && (
                    <span
                        className={`-top-3 right-6 left-6 absolute shrink-0 rounded-sm px-3 py-1 text-center text-[11px] font-bold uppercase tracking-[0.14em] ${t.badge}`}
                    >
                        Most popular
                    </span>
                )}
            </div>

            <p className={`mt-4 flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5 text-4xl font-bold leading-none tracking-tight tabular-nums ${t.amount}`}>
                {formatAmount(plan)}
                <span className={`text-sm font-medium leading-5 ${t.period}`}>{periodLabel(plan, trialDays)}</span>
            </p>

            {cadence && <p className={`mt-1.5 text-xs leading-4 tabular-nums ${t.footnote}`}>{cadence}</p>}

            {plan.summary && <p className={`mt-3 text-sm leading-relaxed ${t.summary}`}>{plan.summary}</p>}

            <div className="mt-5">
                <PlanFeatures plan={plan} featureDefinitions={featureDefinitions} tone={tone} />
            </div>

            {/* mt-auto rather than an empty spacer div: it pushed the CTA to the
                card foot without contributing a box. */}
            <div className="mt-auto">
                <PlanLimits plan={plan} tone={tone} className="mt-5" />

                {plan.footnote && <p className={`mt-4 text-xs leading-relaxed ${t.footnote}`}>{plan.footnote}</p>}

                <PrimaryButton
                    type="button"
                    variant="ledger"
                    onClick={() => onSelect(plan.slug)}
                    disabled={processing}
                    className="mt-4 w-full min-h-11"
                    data-testid={`plan-cta-${plan.slug}`}
                >
                    {ctaLabel(plan)}
                </PrimaryButton>
            </div>
        </article>
    );
}
