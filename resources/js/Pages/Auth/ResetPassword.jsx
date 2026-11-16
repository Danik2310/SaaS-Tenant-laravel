import { useEffect, useRef, useState } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, useForm } from '@inertiajs/react';
import { passwordsMatch } from './parts/passwordFeedback';
import { isPasswordValid, passwordChecks, passwordRuleError } from './parts/passwordPolicy';

export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    // The inline checks run before the request, but the server rule stays
    // authoritative: this only avoids a round trip for an obviously invalid
    // password.
    const [localErrors, setLocalErrors] = useState({});
    const passwordInput = useRef(null);
    const confirmationInput = useRef(null);

    useEffect(() => {
        return () => {
            reset('password', 'password_confirmation');
        };
    }, []);

    const checks = passwordChecks(data.password);
    const confirmationMatches = passwordsMatch(data.password, data.password_confirmation);
    const fieldError = (key) => localErrors[key] ?? errors[key];

    const update = (key, value) => {
        setData(key, value);
        setLocalErrors((previous) => {
            if (!previous[key]) return previous;

            const next = { ...previous };
            delete next[key];

            return next;
        });
    };

    const submit = (event) => {
        event.preventDefault();

        const nextErrors = {};

        if (!isPasswordValid(data.password)) {
            nextErrors.password = passwordRuleError(data.password) ?? 'Choose a stronger password.';
        }

        if (data.password_confirmation === '') {
            nextErrors.password_confirmation = 'Confirm your new password.';
        } else if (!confirmationMatches) {
            nextErrors.password_confirmation = 'Passwords do not match.';
        }

        setLocalErrors(nextErrors);

        if (nextErrors.password) {
            passwordInput.current?.focus();

            return;
        }

        if (nextErrors.password_confirmation) {
            confirmationInput.current?.focus();

            return;
        }

        post(route('password.store'));
    };

    return (
        <GuestLayout>
            <Head title="Reset Password" />

            <div>
                <h1 className="font-display text-2xl leading-tight tracking-tight text-ink">
                    Set a new password
                </h1>
                <p className="mt-1 text-sm text-gray-600">
                    Choose a new password for your account, then log in with it.
                </p>
            </div>

            <div className="mt-6 rounded-xl bg-gray-50 px-3.5 py-3 ring-1 ring-inset ring-gray-200">
                <p className="text-xs font-medium uppercase tracking-wide text-gray-500">Account</p>
                <p className="mt-0.5 break-all text-sm font-medium text-ink" data-testid="reset-email">
                    {data.email || 'No address in this link'}
                </p>
            </div>

            <InputError message={errors.email} className="mt-2" />

            <form onSubmit={submit} className="mt-5" noValidate>
                <div>
                    <InputLabel htmlFor="password" value="New Password" />

                    <TextInput
                        id="password"
                        ref={passwordInput}
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        isFocused={true}
                        invalid={Boolean(fieldError('password'))}
                        aria-describedby="password-rules"
                        aria-invalid={fieldError('password') ? 'true' : undefined}
                        onChange={(event) => update('password', event.target.value)}
                    />

                    <ul
                        id="password-rules"
                        className="mt-2 grid grid-cols-1 gap-1 sm:grid-cols-2"
                        aria-label="Password requirements"
                    >
                        {checks.map((rule) => (
                            <li
                                key={rule.id}
                                data-testid={`password-rule-${rule.id}`}
                                data-met={rule.met ? 'true' : 'false'}
                                className={`flex items-start gap-1.5 text-xs ${
                                    rule.met ? 'text-emerald-700' : 'text-gray-500'
                                }`}
                            >
                                <span aria-hidden="true" className="mt-px leading-none">
                                    {rule.met ? '✓' : '○'}
                                </span>
                                <span>
                                    {rule.label}
                                    <span className="sr-only">
                                        {rule.met ? ' — met' : ' — not met yet'}
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ul>

                    <InputError message={fieldError('password')} className="mt-2" />
                </div>

                <div className="mt-4">
                    <div className="flex items-baseline justify-between gap-3">
                        <InputLabel htmlFor="password_confirmation" value="Confirm Password" />

                        <p
                            role="status"
                            aria-live="polite"
                            data-testid="password-match-status"
                            className={`text-xs ${
                                confirmationMatches ? 'text-emerald-700' : 'text-gray-500'
                            }`}
                        >
                            {data.password_confirmation === ''
                                ? ''
                                : confirmationMatches
                                  ? 'Passwords match'
                                  : 'Passwords do not match yet'}
                        </p>
                    </div>

                    <TextInput
                        id="password_confirmation"
                        ref={confirmationInput}
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        invalid={Boolean(fieldError('password_confirmation'))}
                        aria-invalid={fieldError('password_confirmation') ? 'true' : undefined}
                        onChange={(event) => update('password_confirmation', event.target.value)}
                    />

                    <InputError message={fieldError('password_confirmation')} className="mt-2" />
                </div>

                <div className="mt-6">
                    <PrimaryButton type="submit" className="w-full" disabled={processing}>
                        Reset Password
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
