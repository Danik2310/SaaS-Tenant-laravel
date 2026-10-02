export default function PrimaryButton({
    className = '',
    disabled,
    loading = false,
    loadingText,
    children,
    ...props
}) {
    const isBusy = Boolean(loading || disabled);

    return (
        <button
            {...props}
            className={
                `inline-flex items-center justify-center gap-2 rounded-full border border-transparent bg-brand-700 px-6 py-2.5 text-sm font-semibold text-white transition ease-in-out duration-150 hover:bg-brand-800 active:bg-brand-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-ink focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${
                    isBusy && 'opacity-50'
                } ` + className
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
