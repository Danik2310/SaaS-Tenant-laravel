const STEPS = [
    { key: 'plan', label: 'Plan' },
    { key: 'workspace', label: 'Workspace' },
];

export default function StepIndicator({ current }) {
    const activeIndex = STEPS.findIndex((step) => step.key === current);

    return (
        <ol
            data-testid="step-indicator"
            className="flex items-center gap-2 text-xs font-semibold"
            aria-label="Signup progress"
        >
            {STEPS.map((step, index) => {
                const isDone = index < activeIndex;
                const isCurrent = index === activeIndex;

                return (
                    <li key={step.key} className="flex items-center gap-2">
                        <span
                            data-testid={`step-${index + 1}`}
                            aria-current={isCurrent ? 'step' : undefined}
                            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 ${
                                isCurrent
                                    ? 'bg-ink text-white'
                                    : isDone
                                      ? 'bg-brand-700 text-white'
                                      : 'bg-gray-100 text-gray-500'
                            }`}
                        >
                            <span
                                aria-hidden="true"
                                className={`inline-flex h-4 w-4 items-center justify-center rounded-full text-[10px] ${
                                    isCurrent ? 'bg-white/20' : 'bg-black/10'
                                }`}
                            >
                                {index + 1}
                            </span>
                            {step.label}
                        </span>

                        {index < STEPS.length - 1 && (
                            <span
                                aria-hidden="true"
                                className={`h-px w-6 ${isDone ? 'bg-brand-300' : 'bg-gray-200'}`}
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