// Surface tones for the public register page. The page field is bg-ink, so the
// featured card is the one thing that is *light*: making the recommended plan
// the brightest object inverts the usual "dark card lifts off white" trick and
// leaves brand orange as the only accent on the page.
//
// Measured contrast, not estimated (WCAG 2.1 relative luminance). The plain
// card sits on bg-white/[0.05] = #161616 over an ink #0A0A0A field:
//   border-white/35 on ink        3.15:1    component boundary (AA 1.4.11)
//   border-white/35 on #161616    2.88:1    same border against its own card
//   divide-white/20 on #161616    1.89:1    internal grouping, no floor
//   text-white/70   on #161616    9.23:1
//   text-white/50   on #161616    5.31:1    small-text floor (AA)
//   brand-400       on #161616    8.00:1
// Against white, for the featured card:
//   text-ink/60   on white         5.7:1    small-text floor
//   brand-700     #C2410C on white 4.2:1    eyebrow / check glyphs (AA large)
//   white on brand-500 #F97316     2.80:1   FAILS AA - see badge below
//   white on brand-700 #C2410C     5.11:1   badge text (AA small)
//   white on brand-800 #9A3412     7.29:1   badge text on hover
// // The badge therefore sits on brand-700, not brand-500: brand-500 is the brand
// hue but white text on it is under the 3:1 large-text threshold, and the badge
// is 11px.
export const CARD_TONES = {
    featured: {
        surface: 'bg-white text-ink ring-1 ring-brand-500/40 transition-colors duration-300 ease-out',
        // No border-* here: a border on this tone would only ever restate the
        // ring, so the hover affordance is the ring alone.
        hoverShadow: 'hover:ring-brand-500/70',
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
        // The card has to be findable against the ink field, so the outline
        // carries the separation: border-white/35 is 3.15:1, which is the 3:1
        // WCAG 1.4.11 asks for on a component boundary. At white/10 it was
        // 1.26:1 and the card read as a smudge. The surface stays low-key on
        // purpose - featured is meant to be the one light object on the page,
        // so plain sits just off the field instead of on the way to white.
        surface: 'rounded-sm border border-white/35 bg-white/[0.05] text-white transition-colors duration-300 ease-out',
        // Above the resting white/35, never below it, or hover dims the card.
        hoverShadow: 'hover:border-white/60',
        eyebrow: 'text-brand-400',
        amount: 'text-white',
        period: 'text-white/70',
        summary: 'text-white/70',
        footnote: 'text-white/50',
        feature: 'text-white/70',
        limitLabel: 'text-white/50',
        limitValue: 'font-semibold tabular-nums text-white',
        hairline: 'border-white/20',
        hairlineDivide: 'divide-white/20',
        check: 'text-brand-400',
        badge: 'bg-white/10 text-white ring-1 ring-inset ring-white/20',
        focusRing:
            'text-brand-300 hover:text-brand-200 focus-visible:ring-brand-400 focus-visible:ring-offset-2 focus-visible:ring-offset-ink',
    },
};
