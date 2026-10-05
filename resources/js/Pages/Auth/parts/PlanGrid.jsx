import PlanCard from './PlanCard';
import { planSpanClasses } from './planFormat';

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

            <div data-testid="plan-grid" className="grid grid-cols-1 gap-5 md:grid-cols-2">
                {plans.map((plan, index) => (
                    <PlanCard
                        key={plan.slug}
                        plan={plan}
                        featureDefinitions={featureDefinitions}
                        trialDays={trialDays}
                        index={index}
                        onSelect={onSelect}
                        processing={processing}
                        className={planSpanClasses(plans.length, index)}
                    />
                ))}
            </div>

            <p className="mt-8 max-w-prose border-t border-white/10 pt-6 text-xs leading-relaxed text-white/50">
                Paid plans are billed securely at checkout. Trials and free plans have no charge.
            </p>
        </section>
    );
}
