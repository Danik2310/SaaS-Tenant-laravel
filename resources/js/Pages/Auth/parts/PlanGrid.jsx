import PlanCard from './PlanCard';

export default function PlanGrid({
    plans,
    featureDefinitions,
    onSelect,
    processing,
    headingId = 'plan-step-heading',
}) {
    return (
        <section aria-labelledby={headingId}>
            <div role="group" data-testid="plan-grid" aria-label="Available plans">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {plans.map((plan, index) => (
                        <PlanCard
                            key={plan.slug}
                            plan={plan}
                            featureDefinitions={featureDefinitions}
                            onSelect={onSelect}
                            processing={processing}
                            className={
                                index === plans.length - 1 && plans.length % 2 === 1 ? 'sm:col-span-2 lg:col-span-1' : ''
                            }
                        />
                    ))}
                </div>
            </div>

            <p className="mt-6 text-center text-xs text-gray-500">
                Paid plans are billed securely at checkout. Trials and free plans have no charge.
            </p>
        </section>
    );
}