/**
 * Native `<select>` styled to match the register page's dark `TextInput` tone.
 *
 * `color-scheme: dark` tells the browser to render the OS dropdown and its
 * scrollbar with a dark palette, so the white options stay readable once the
 * popup opens (the `bg-transparent` control would otherwise leak white text
 * onto a light popup in some engines).
 */
export default function DarkSelect({
    options = [],
    placeholder = 'Select an option',
    tone = 'dark',
    invalid = false,
    className = '',
    ...props
}) {
    const styles = {
        light: 'rounded-xl px-3.5 py-2.5 shadow-sm ring-1 ring-inset',
        dark: `rounded-sm border px-3.5 py-2.5 text-white [color-scheme:dark] focus:border-brand-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-ink disabled:cursor-not-allowed disabled:opacity-60 ${
            invalid ? 'border-red-400' : 'border-white/35'
        }`,
    };

    return (
        <select
            {...props}
            className={`${styles[tone] ?? styles.light} block w-full ${className}`}
        >
            <option value="">{placeholder}</option>

            {options.map((option) => (
                <option key={`${option.value}:${option.label}`} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}