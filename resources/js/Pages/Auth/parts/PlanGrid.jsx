import PlanCard from './PlanCard';
import { isWideCard, planSpanClasses } from './planFormat';

export default function PlanGrid({
    plans,
    featureDefinitions,
    onSelect,
    processing,
    trialDays = 14,
}) {
    return (
        <section aria-labelledby="plan-grid-heading">
            <h2 id="plan-grid-heading" className="sr-only">
                Available plans
            </h2>

            <div
                data-testid="plan-grid"
                className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:gap-5"
            >
                {plans.map((plan, index) => (
                    <PlanCard
                        key={plan.slug}
                        plan={plan}
                        featureDefinitions={featureDefinitions}
                        trialDays={trialDays}
                        index={index}
                        onSelect={onSelect}
                        processing={processing}
                        wide={isWideCard(plans.length, index)}
                        className={planSpanClasses(plans.length, index)}
                    />
                ))}
            </div>

            <p className="mt-8 border-t border-gray-100 pt-6 text-center text-xs leading-relaxed text-gray-500 mx-auto max-w-prose">
                Paid plans are billed securely at checkout. Trials and free plans have no charge.
            </p>
        </section>
    );
}
