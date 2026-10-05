import { forwardRef, useEffect, useRef } from 'react';

// `dark` is the register field on the ink surface. Expressed as utilities rather
// than a CSS class so it composes with Tailwind the way the rest of the page
// does; the default stays byte-identical so dashboard and tenant forms are
// untouched. Border is white/35 because an input carries no label of its own
// and its outline is the only cue that it is a control: WCAG SC 1.4.11 wants
// 3:1 against the background (3.14:1 here).
const TONES = {
    light: 'rounded-xl px-3.5 py-2.5 shadow-sm ring-1 ring-inset',
    dark: 'rounded-sm border border-white/35 bg-transparent px-3.5 py-2.5 text-white placeholder:text-white/50 focus:border-brand-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-ink',
};

export default forwardRef(function TextInput(
    { type = 'text', className = '', isFocused = false, invalid = false, tone = 'light', ...props },
    ref
) {
    const input = ref ? ref : useRef();

    useEffect(() => {
        if (isFocused) {
            input.current.focus();
        }
    }, []);

    const dark = tone === 'dark';

    const state = invalid
        ? dark
            ? 'border-red-400'
            : 'border-red-500 focus:border-red-600 focus:ring-red-500'
        : dark
          ? ''
          : 'border-gray-200 focus:border-brand-500 focus:ring-brand-500';

    return (
        <input
            {...props}
            type={type}
            className={`${TONES[tone] ?? TONES.light} ${state} ${className}`}
            ref={input}
        />
    );
});
