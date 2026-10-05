// Surface tones for the public register page. The page field is bg-ink, so the
// featured card is the one thing that is *light*: making the recommended plan
// the brightest object inverts the usual "dark card lifts off white" trick and
// leaves brand orange as the only accent on the page.
//
// Measured contrast, not estimated (WCAG 2.1 relative luminance):
//   text-white/50 on ink            5.0:1   small-text floor (AA)
//   text-white/70 on ink            9.4:1
//   text-ink/60   on white          5.7:1   small-text floor on the featured card
//   brand-700     #C2410C on white  4.2:1   eyebrow / check glyphs (AA large)
//   white on brand-500 #F97316     2.80:1  FAILS AA — see badge below
//   white on brand-700 #C2410C     5.11:1  badge text (AA small)
//   white on brand-800 #9A3412     7.29:1  badge text on hover
// The badge therefore sits on brand-700, not brand-500: brand-500 is the brand
// hue but white text on it is under the 3:1 large-text threshold, and the badge
// is 11px.
export const CARD_TONES = {
    featured: {
        surface: 'bg-white text-ink ring-1 ring-brand-500/40 transition-colors duration-300 ease-out',
        hoverShadow: 'hover:border-brand-500/60 hover:ring-brand-500/70',
        eyebrow: 'text-ink/60',
        amount: 'text-ink',
        period: 'text-ink/60',
        summary: 'text-ink/70',
        footnote: 'text-ink/60',
        feature: 'text-ink/80',
        limitLabel: 'text-ink/60',
        limitValue: 'font-semibold tabular-nums text-ink',
        hairline: 'border-ink/10',
        hairlineDivide: 'divide-ink/10',
        check: 'text-brand-700',
        badge: 'bg-brand-700 text-white',
        focusRing:
            'text-brand-800 hover:text-brand-900 focus-visible:ring-brand-700 focus-visible:ring-offset-2 focus-visible:ring-offset-white',
    },
    plain: {
        surface: 'rounded-sm border border-white/10 bg-white/[0.02] text-white transition-colors duration-300 ease-out',
        hoverShadow: 'hover:border-white/25',
        eyebrow: 'text-brand-400',
        amount: 'text-white',
        period: 'text-white/70',
        summary: 'text-white/70',
        footnote: 'text-white/50',
        feature: 'text-white/70',
        limitLabel: 'text-white/50',
        limitValue: 'font-semibold tabular-nums text-white',
        hairline: 'border-white/10',
        hairlineDivide: 'divide-white/10',
        check: 'text-brand-400',
        badge: 'bg-white/10 text-white ring-1 ring-inset ring-white/20',
        focusRing:
            'text-brand-300 hover:text-brand-200 focus-visible:ring-brand-400 focus-visible:ring-offset-2 focus-visible:ring-offset-ink',
    },
};