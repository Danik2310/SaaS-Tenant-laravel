import { CARD_TONES } from './planTones.js';
import { LIMIT_ROWS, limitValue } from './planFormat';

/**
 * The one <dl> renderer for plan limits. PlanCard and PlanSummary show the
 * same LIMIT_ROWS in the same order on different surfaces; only the tone and
 * the outer spacing differ.
 */
export default function PlanLimits({ plan, tone = 'plain', className = '' }) {
    const t = CARD_TONES[tone];

    return (
        <dl
            data-testid={`plan-limits-${plan.slug}`}
            aria-label={`Included in ${plan.name}`}
            className={`border-t ${t.hairline} ${t.hairlineDivide} divide-y pt-4 text-[13px] leading-5 ${className}`}
        >
            {LIMIT_ROWS.map((row) => (
                <div key={row.key} className="grid grid-cols-[1fr_auto] items-baseline gap-x-4 py-1.5">
                    <dt className={`truncate ${t.limitLabel}`}>{row.label}</dt>
                    <dd className={`shrink-0 whitespace-nowrap text-right ${t.limitValue}`}>
                        {limitValue(plan.limits?.[row.key], row.unit)}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
