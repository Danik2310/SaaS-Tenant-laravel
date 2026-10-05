export default function PrimaryButton({
    className = '',
    variant = 'primary',
    disabled,
    loading = false,
    loadingText,
    children,
    ...props
}) {
    const isBusy = Boolean(loading || disabled);

    // `primary` keeps the dashboard's pill on a light surface: its focus ring is
    // ink, which is invisible on the dark register field — hence the ring swap and
    // the square radius in `ledger`, not a restyle of the shared default.
    const variants = {
        primary:
            'rounded-full border border-transparent transition ease-in-out duration-150 focus-visible:ring-ink focus-visible:ring-offset-2',
        ledger:
            'rounded-sm border border-transparent sl-press transition-colors duration-300 ease-out focus-visible:ring-brand-500 focus-visible:ring-offset-ink',
    };

    return (
        <button
            {...props}
            className={
                `inline-flex items-center justify-center gap-2 bg-brand-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 active:bg-brand-900 focus:outline-none focus-visible:ring-2 disabled:cursor-not-allowed disabled:opacity-50 ${
                    variants[variant] ?? variants.primary
                } ${isBusy ? 'opacity-50' : ''} ` + className
            }
            disabled={isBusy}
            aria-busy={loading ? 'true' : undefined}
        >
            {loading && (
                <svg
                    aria-hidden="true"
                    viewBox="0 0 24 24"
                    fill="none"
                    className="h-4 w-4 animate-spin"
                >
                    <circle
                        cx="12"
                        cy="12"
                        r="9"
                        stroke="currentColor"
                        strokeOpacity="0.3"
                        strokeWidth="3"
                    />
                    <path
                        d="M21 12a9 9 0 0 0-9-9"
                        stroke="currentColor"
                        strokeWidth="3"
                        strokeLinecap="round"
                    />
                </svg>
            )}
            {loading && loadingText ? loadingText : children}
        </button>
    );
}
