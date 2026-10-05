import { useId } from 'react';

/**
 * Collapsible group for the non-required signup fields. The panel is always
 * mounted and only toggled with `hidden`, so server-side validation errors on
 * hidden inputs keep their DOM nodes (and their labels) intact.
 */
export default function OptionalDetails({ open, onToggle, label, hint, children }) {
    const panelId = useId();

    return (
        <div className="mt-8">
            <button
                type="button"
                onClick={onToggle}
                aria-expanded={open}
                aria-controls={panelId}
                data-testid="optional-details-toggle"
                className="flex w-full items-center justify-between gap-3 rounded-sm border border-white/10 bg-white/[0.02] px-4 py-3 text-left text-sm font-semibold text-white transition-colors duration-150 ease-out hover:border-brand-500/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-ink"
            >
                <span>
                    {label}
                    {hint && <span className="mt-0.5 block text-xs font-normal text-white/50">{hint}</span>}
                </span>

                <span
                    aria-hidden="true"
                    className="shrink-0 text-lg leading-none text-white/50"
                >
                    {open ? '−' : '+'}
                </span>
            </button>

            <div id={panelId} hidden={!open} data-testid="optional-details-panel" className="mt-5 space-y-5">
                {children}
            </div>
        </div>
    );
}