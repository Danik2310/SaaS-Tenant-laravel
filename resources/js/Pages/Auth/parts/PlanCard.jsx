import PrimaryButton from '@/Components/PrimaryButton';
import {
    LIMIT_ROWS,
    ctaLabel,
    featureLabel,
    formatPrice,
    hiddenFeatureCount,
    isRecommended,
    limitValue,
    visibleFeatures,
} from './planFormat';

export default function PlanCard({ plan, featureDefinitions, onSelect, processing, className = '' }) {
    const recommended = isRecommended(plan);
    const features = visibleFeatures(plan);
    const hidden = hiddenFeatureCount(plan);

    const surface = recommended
        ? 'bg-ink text-white ring-1 ring-brand-500'
        : 'bg-white text-gray-900 ring-1 ring-gray-200 hover:ring-brand-300';
    const mutedText = recommended ? 'text-gray-300' : 'text-gray-600';
    const hairline = recommended ? 'border-white/10' : 'border-gray-100';
    const limitLabel = recommended ? 'text-gray-400' : 'text-gray-500';
    const limitValueClass = recommended ? 'text-white' : 'text-gray-800';

    return (
        <article
            data-testid={`plan-card-${plan.slug}`}
            data-recommended={recommended ? 'true' : 'false'}
            className={`flex h-full flex-col rounded-2xl p-6 transition-shadow duration-300 ease-out hover:shadow-lg hover:shadow-brand-500/10 ${surface} ${className}`}
        >
            <div className="flex items-start justify-between gap-2">
                <h3
                    className={`text-sm font-bold uppercase tracking-[0.14em] ${
                        recommended ? 'text-brand-400' : 'text-gray-900'
                    }`}
                >
                    {plan.name}
                </h3>

                {recommended && (
                    <span className="shrink-0 rounded-full bg-brand-500 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-white">
                        Most popular
                    </span>
                )}
            </div>

            <p
                className={`mt-4 font-display text-4xl leading-none ${
                    recommended ? 'text-white' : 'text-ink'
                }`}
            >
                {formatPrice(plan)}
            </p>

            <ul className={`mt-5 space-y-1.5 text-sm ${mutedText}`}>
                {features.length === 0 ? (
                    <li>No feature add-ons</li>
                ) : (
                    features.map((key) => (
                        <li key={key} className="flex items-start gap-2">
                            <span aria-hidden="true" className="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-brand-500" />
                            <span>{featureLabel(featureDefinitions, key)}</span>
                        </li>
                    ))
                )}

                {hidden > 0 && <li className={mutedText}>+{hidden} more</li>}
            </ul>

            <dl className={`mt-5 grid grid-cols-2 gap-x-4 gap-y-2 border-t ${hairline} pt-4 text-sm`}>
                {LIMIT_ROWS.map((row) => (
                    <div key={row.key} className="flex items-baseline justify-between gap-2">
                        <dt className={limitLabel}>{row.label}</dt>
                        <dd className={`font-medium tabular-nums ${limitValueClass}`}>
                            {limitValue(plan.limits?.[row.key])}
                        </dd>
                    </div>
                ))}
            </dl>

            <PrimaryButton
                type="button"
                onClick={() => onSelect(plan.slug)}
                disabled={processing}
                className="mt-6 w-full"
                data-testid={`plan-cta-${plan.slug}`}
            >
                {ctaLabel(plan)}
            </PrimaryButton>
        </article>
    );
}