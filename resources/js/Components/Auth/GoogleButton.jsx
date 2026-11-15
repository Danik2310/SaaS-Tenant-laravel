/**
 * "Continue with Google" — the tenant login/register social entry point, and the
 * "Sign up with Google" entry on the central workspace signup.
 *
 * The URLs are built server-side (`OAuthProviderLinks`), because they must point
 * at the *central* OAuth endpoint while the page itself is served from a tenant
 * host. When no provider is configured the component renders nothing at all, so
 * an environment without Google credentials keeps the plain email/password form.
 *
 * `label` is rendered verbatim (e.g. "Sign up with Google"); without it the
 * button keeps the generic "Continue with {provider}" copy. `tone` picks the
 * palette for a light guest card or the dark ledger signup page.
 *
 * `divider` controls the "or" rule: `true`/`'after'` trails the button (the
 * form follows it, as on register), `'before'` leads it (the login form sits
 * above the button), and `false` drops it entirely.
 */
const MARKS = {
    google: (
        <svg aria-hidden="true" viewBox="0 0 24 24" className="h-5 w-5">
            <path
                fill="#4285F4"
                d="M23.49 12.27c0-.79-.07-1.54-.2-2.27H12v4.51h6.47a5.53 5.53 0 0 1-2.4 3.63v3h3.86c2.26-2.09 3.56-5.17 3.56-8.87Z"
            />
            <path
                fill="#34A853"
                d="M12 24c3.24 0 5.96-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96H1.29v3.09A11.99 11.99 0 0 0 12 24Z"
            />
            <path
                fill="#FBBC05"
                d="M5.27 14.29a7.2 7.2 0 0 1 0-4.58V6.62H1.29a12 12 0 0 0 0 10.76l3.98-3.09Z"
            />
            <path
                fill="#EA4335"
                d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.69 1.29 6.62l3.98 3.09C6.22 6.86 8.87 4.75 12 4.75Z"
            />
        </svg>
    ),
};

const TONES = {
    light: {
        button:
            'border-gray-300 bg-white text-ink hover:bg-gray-50 focus-visible:ring-ink focus-visible:ring-offset-2',
        rule: 'bg-gray-200',
        ruleLabel: 'text-gray-500',
    },
    dark: {
        button:
            'border-white/20 bg-white/[0.04] text-white hover:bg-white/[0.09] focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-ink',
        rule: 'bg-white/15',
        ruleLabel: 'text-white/50',
    },
};

export default function GoogleButton({
    providers = [],
    label = null,
    tone = 'light',
    divider = true,
}) {
    if (!providers.length) {
        return null;
    }

    const palette = TONES[tone] ?? TONES.light;
    const ruleBefore = divider === 'before';
    const ruleAfter = divider === true || divider === 'after';

    return (
        <div className="mt-6">
            {ruleBefore && <Rule palette={palette} className="mb-6" />}

            <div className="space-y-3">
                {providers.map((provider) => (
                    <a
                        key={provider.id}
                        href={provider.url}
                        className={`flex w-full items-center justify-center gap-3 rounded-full border px-6 py-2.5 text-sm font-semibold transition ease-in-out duration-150 focus:outline-none focus-visible:ring-2 ${palette.button}`}
                    >
                        {MARKS[provider.id] ?? null}
                        <span>{label ?? `Continue with ${provider.label}`}</span>
                    </a>
                ))}
            </div>

            {ruleAfter && <Rule palette={palette} className="mt-6" />}
        </div>
    );
}

/** The "or" rule separating the social button from the email form. */
function Rule({ palette, className }) {
    return (
        <div className={`flex items-center gap-3 ${className}`} aria-hidden="true">
            <span className={`h-px flex-1 ${palette.rule}`} />
            <span className={`text-xs font-medium uppercase tracking-wide ${palette.ruleLabel}`}>
                or
            </span>
            <span className={`h-px flex-1 ${palette.rule}`} />
        </div>
    );
}
