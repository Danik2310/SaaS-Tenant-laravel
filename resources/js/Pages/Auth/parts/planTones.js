// Contrast floor notes: do not use gray-500/600 on the 'ink' (recommended) surface
// (gray-600 on #0A0A0A is ~2.62:1 and fails WCAG). Light surface is fine.
export const CARD_TONES = {
    ink: {
        surface:
            'bg-ink text-white shadow-xl shadow-brand-500/25 ring-1 ring-brand-500/50 transition-all duration-300 ease-out',
        hoverShadow: 'hover:shadow-2xl hover:shadow-brand-500/30',
        eyebrow: 'text-brand-400',
        amount: 'text-white',
        period: 'text-white/70',
        summary: 'text-gray-300',
        footnote: 'text-gray-400',
        feature: 'text-gray-200',
        limitLabel: 'text-gray-400',
        limitValue: 'font-semibold tabular-nums text-white',
        hairline: 'border-white/15',
        check: 'text-brand-400',
        badge: 'bg-brand-400 text-ink ring-1 ring-inset ring-brand-200',
        focusRing:
            'text-brand-300 hover:text-brand-200 focus-visible:ring-brand-400 focus-visible:ring-offset-2 focus-visible:ring-offset-ink',
    },
    light: {
        surface:
            'bg-white text-gray-900 shadow-sm ring-1 ring-gray-900/[0.07] transition-all duration-300 ease-out',
        hoverShadow: 'hover:-translate-y-1 hover:shadow-lg hover:shadow-brand-500/10 hover:ring-brand-300',
        eyebrow: 'text-gray-500',
        amount: 'text-ink',
        period: 'text-gray-500',
        summary: 'text-gray-600',
        footnote: 'text-gray-500',
        feature: 'text-gray-700',
        limitLabel: 'text-gray-500',
        limitValue: 'font-semibold tabular-nums text-gray-900',
        hairline: 'border-gray-200',
        check: 'text-brand-600',
        badge: 'bg-brand-100 text-brand-800 ring-1 ring-inset ring-brand-200',
        focusRing:
            'text-brand-700 hover:text-brand-800 focus-visible:ring-ink focus-visible:ring-offset-2 focus-visible:ring-offset-white',
    },
};
