const FIELD_ERROR_LABELS = {
    name: 'Workspace name',
    email: 'Email',
    company_name: 'Company name',
    subdomain: 'Workspace address',
    password: 'Password',
    password_confirmation: 'Password confirmation',
    terms: 'Terms acceptance',
};

const GLOBAL_ERROR_KEYS = ['provisioning', 'plan', 'website'];

export default function ErrorSummary({ errors, onFocusField }) {
    const globalEntries = GLOBAL_ERROR_KEYS.filter((key) => errors?.[key]).map((key) => ({
        key,
        message: errors[key],
        focusable: false,
    }));

    const fieldEntries = Object.keys(errors ?? {})
        .filter((key) => !GLOBAL_ERROR_KEYS.includes(key))
        .map((key) => ({
            key,
            message: errors[key],
            focusable: true,
            label: FIELD_ERROR_LABELS[key] ?? key,
        }));

    if (globalEntries.length === 0 && fieldEntries.length === 0) {
        return null;
    }

    return (
        <div
            role="alert"
            data-testid="form-error-banner"
            className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
        >
            {globalEntries.length > 0 && (
                <ul className="space-y-1">
                    {globalEntries.map((entry) => (
                        <li key={entry.key}>{entry.message}</li>
                    ))}
                </ul>
            )}

            {fieldEntries.length > 0 && (
                <ul className={globalEntries.length > 0 ? 'mt-2 space-y-1' : 'space-y-1'}>
                    {fieldEntries.map((entry) => (
                        <li key={entry.key} className="flex flex-wrap items-baseline gap-x-2">
                            <span>{entry.message}</span>
                            <button
                                type="button"
                                onClick={() => onFocusField(entry.key)}
                                className="font-semibold underline underline-offset-2 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-ink focus-visible:ring-offset-2"
                            >
                                Go to {entry.label.toLowerCase()}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}