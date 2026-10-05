import PlanCard from './PlanCard';

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

            {/* Three columns from lg. The catalog holds five plans, so 2-up ended
                on a lone full-width card and an odd row rhythm; 3-up gives
                3 + 2 with every card the same width and no span rule to
                maintain. Cards stay equal height via h-full inside PlanCard. */}
            <div data-testid="plan-grid" className="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3 lg:gap-6">
                {plans.map((plan, index) => (
                    <PlanCard
                        key={plan.slug}
                        plan={plan}
                        featureDefinitions={featureDefinitions}
                        trialDays={trialDays}
                        index={index}
                        onSelect={onSelect}
                        processing={processing}
                    />
                ))}
            </div>

            <p className="mt-8 max-w-prose border-t border-white/10 pt-6 text-xs leading-relaxed text-white/50">
                Paid plans are billed securely at checkout. Trials and free plans have no charge.
            </p>
        </section>
    );
}
