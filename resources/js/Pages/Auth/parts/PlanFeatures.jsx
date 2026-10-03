import { useId, useState } from 'react';
import { CARD_TONES } from './planTones.js';
import { MAX_VISIBLE_FEATURES, featureLabel, orderedFeatures } from './planFormat';

function CheckIcon({ tone }) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2.2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            className={`mt-[3px] h-4 w-4 shrink-0 ${CARD_TONES[tone].check}`}
        >
            <path d="m5 12.5 4.5 4.5L19 7" />
        </svg>
    );
}

/**
 * Feature list with progressive disclosure.
 *
 * Collapsed it renders the first MAX_VISIBLE_FEATURES in catalogue order and
 * a "+N more" control that reveals the rest in place. The hidden rows are
 * never mounted, so the collapsed DOM — and therefore textContent — reflects
 * exactly what is on screen.
 */
export default function PlanFeatures({ plan, featureDefinitions, tone = 'light' }) {
    const [expanded, setExpanded] = useState(false);
    const panelId = useId();
    const t = CARD_TONES[tone];

    const ordered = orderedFeatures(plan, featureDefinitions);
    const shown = expanded ? ordered : ordered.slice(0, MAX_VISIBLE_FEATURES);
    const hidden = Math.max(0, ordered.length - MAX_VISIBLE_FEATURES);

    return (
        <div className="mt-5">
            <ul id={panelId} className={`space-y-2 leading-5 text-sm ${t.feature}`}>
                {shown.length === 0 ? (
                    <li className={`${t.summary} italic`}>Catalog, categories and a stock overview</li>
                ) : (
                    shown.map((key) => (
                        <li key={key} className="flex items-start gap-2.5">
                            <CheckIcon tone={tone} />
                            <span className="min-w-0 break-words">{featureLabel(featureDefinitions, key)}</span>
                        </li>
                    ))
                )}
            </ul>

            {hidden > 0 && (
                <button
                    type="button"
                    onClick={() => setExpanded((value) => !value)}
                    aria-expanded={expanded}
                    aria-controls={panelId}
                    data-testid={`plan-features-toggle-${plan.slug}`}
                    className={`-my-1 mt-3 inline-flex min-h-6 items-center gap-1 rounded py-1 text-xs font-semibold underline underline-offset-4 transition-colors duration-150 ease-out focus:outline-none focus-visible:ring-2 ${t.focusRing}`}
                >
                    +{hidden} more
                    <span className="sr-only"> features included in {plan.name}</span>
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2.4"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        aria-hidden="true"
                        className={`h-3.5 w-3.5 transition-transform duration-300 ease-out ${expanded ? 'rotate-180' : ''}`}
                    >
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                </button>
            )}
        </div>
    );
}
