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
    className = '',
    wide = false,
}) {
    const recommended = isRecommended(plan);
    const tone = recommended ? 'ink' : 'light';
    const t = CARD_TONES[tone];
    const cadence = cadenceNote(plan);
    const titleId = `plan-card-${plan.slug}-title`;

    const cardClasses = `sl-plan-card animate-sli-fade-in rounded-2xl p-5 sm:p-6 relative overflow-visible ${t.surface} ${t.hoverShadow} ${className} ${
        wide ? 'md:grid md:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)] md:items-start md:gap-x-10' : 'flex h-full flex-col'
    }`;

    return (
        <article
            data-testid={`plan-card-${plan.slug}`}
            data-recommended={recommended ? 'true' : 'false'}
            aria-labelledby={titleId}
            style={index ? { animationDelay: `${Math.min(index, 5) * 90}ms` } : undefined}
            className={cardClasses}
        >
            <div className={wide ? 'flex flex-col' : ''}>
                <div className="flex items-start justify-between gap-3">
                    <h3
                        id={titleId}
                        className={`min-w-0 truncate text-base font-bold uppercase leading-5 tracking-[0.12em] ${t.eyebrow}`}
                    >
                        {plan.name}
                    </h3>

                    {recommended && (
                        <span
                            className={`-top-3 right-6 left-6 absolute shrink-0 rounded-full px-3 py-1 text-center text-[11px] font-bold uppercase tracking-[0.14em] ${t.badge}`}
                        >
                            <span aria-hidden="true" className="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-current align-middle" />
                            Most popular
                        </span>
                    )}
                </div>

                <p className={`mt-4 flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5 font-display text-[2.5rem] leading-none tracking-tight tabular-nums ${t.amount}`}>
                    {formatAmount(plan)}
                    <span className={`font-sans text-sm font-medium leading-5 ${t.period}`}>
                        {periodLabel(plan, trialDays)}
                    </span>
                </p>

                {cadence && <p className={`mt-1.5 text-xs leading-4 tabular-nums ${t.footnote}`}>{cadence}</p>}

                {plan.summary && (
                    <p className={`mt-3 text-sm leading-relaxed ${t.summary}`}>{plan.summary}</p>
                )}
            </div>

            <div className={wide ? 'mt-5 flex flex-col md:mt-0' : ''}>
                <PlanFeatures plan={plan} featureDefinitions={featureDefinitions} tone={tone} />

                <div className={wide ? 'mt-5 flex flex-col' : 'mt-auto'} />
                <div className={wide ? 'flex flex-col' : ''}>
                    <PlanLimits plan={plan} tone={tone} className="mt-5" />

                    {plan.footnote && (
                        <p className={`mt-4 text-xs leading-relaxed ${t.footnote}`}>{plan.footnote}</p>
                    )}

                    <PrimaryButton
                        type="button"
                        onClick={() => onSelect(plan.slug)}
                        disabled={processing}
                        className={wide ? 'mt-4 w-full md:w-56 md:self-end' : 'mt-4 w-full min-h-11'}
                        data-testid={`plan-cta-${plan.slug}`}
                    >
                        {ctaLabel(plan)}
                    </PrimaryButton>
                </div>
            </div>
        </article>
    );
}
