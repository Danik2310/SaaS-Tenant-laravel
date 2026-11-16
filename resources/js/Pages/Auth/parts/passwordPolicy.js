/**
 * Client mirror of the reset password rule in `NewPasswordController`:
 * `Password::min(8)->mixedCase()->numbers()->symbols()`.
 *
 * The regexes are copied from Laravel's `Rules\Password` so the inline
 * checklist cannot drift from what the server accepts. `symbols()` treats
 * Unicode separator, symbol, and punctuation characters as symbols, so a
 * plain space satisfies it — same as the server.
 */
export const PASSWORD_RULES = [
    {
        id: 'length',
        label: 'At least 8 characters',
        message: 'Use at least 8 characters.',
        test: (value) => value.length >= 8,
    },
    {
        id: 'lowercase',
        label: 'One lowercase letter',
        message: 'Add a lowercase letter.',
        test: (value) => /\p{Ll}/u.test(value),
    },
    {
        id: 'uppercase',
        label: 'One uppercase letter',
        message: 'Add an uppercase letter.',
        test: (value) => /\p{Lu}/u.test(value),
    },
    {
        id: 'number',
        label: 'One number',
        message: 'Add a number.',
        test: (value) => /\p{N}/u.test(value),
    },
    {
        id: 'symbol',
        label: 'One symbol, for example ! ? @ #',
        message: 'Add a symbol, for example ! ? @ #.',
        test: (value) => /\p{Z}|\p{S}|\p{P}/u.test(value),
    },
];

/** Every rule with its current state, in display order. */
export const passwordChecks = (value = '') =>
    PASSWORD_RULES.map((rule) => ({ ...rule, met: rule.test(value) }));

export const isPasswordValid = (value = '') => PASSWORD_RULES.every((rule) => rule.test(value));

/** Message for the first unmet rule, or null when the password passes. */
export const passwordRuleError = (value = '') =>
    PASSWORD_RULES.find((rule) => !rule.test(value))?.message ?? null;
