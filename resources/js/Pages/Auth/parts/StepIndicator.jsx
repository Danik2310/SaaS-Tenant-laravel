const STEPS = [
    { key: 'plan', label: 'Plan' },
    { key: 'workspace', label: 'Workspace' },
    { key: 'payment', label: 'Payment' },
];

export default function StepIndicator({ current }) {
    const activeIndex = STEPS.findIndex((step) => step.key === current);

    return (
        <ol data-testid="step-indicator" className="flex items-center gap-3" aria-label="Signup progress">
            {STEPS.map((step, index) => {
                const isDone = index < activeIndex;
                const isCurrent = index === activeIndex;

                return (
                    <li key={step.key} className="flex items-center gap-3">
                        <span
                            data-testid={`step-${index + 1}`}
                            aria-current={isCurrent ? 'step' : undefined}
                            className={`inline-flex items-center gap-2 rounded-sm py-1 text-xs font-bold uppercase tracking-[0.2em] ${
                                isCurrent ? 'text-white' : isDone ? 'text-brand-400' : 'text-white/50'
                            }`}
                        >
                            {/* The numeral is decorative: at text-white/50 it sits near 5:1, but
                                it is the label beside it that names the step for assistive tech.
                                The sr-only text at the bottom of the list carries the count. */}
                            <span
                                aria-hidden="true"
                                className={`inline-flex h-5 w-5 items-center justify-center rounded-sm font-display text-[0.7rem] leading-none ${
                                    isCurrent ? 'bg-brand-500 text-white' : isDone ? 'bg-brand-500/20 text-brand-400' : 'bg-white/10 text-white/60'
                                }`}
                            >
                                {index + 1}
                            </span>
                            {step.label}
                        </span>

                        {index < STEPS.length - 1 && (
                            <span
                                aria-hidden="true"
                                className={`h-px w-10 transition-colors duration-300 ease-out ${isDone ? 'bg-brand-500/50' : 'bg-white/15'}`}
                            />
                        )}
                    </li>
                );
            })}

            <span className="sr-only">
                Step {activeIndex + 1} of {STEPS.length}
            </span>
        </ol>
    );
}