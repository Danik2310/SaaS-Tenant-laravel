import { useEffect, useState } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, router } from '@inertiajs/react';

/** How often the waiting screen asks the server whether the link was used. */
const POLL_INTERVAL_MS = 5000;

const STATUS_MESSAGES = {
    'verification-link-sent': 'A new verification link is on its way.',
    'verification-preparing':
        'Your workspace is still being prepared. Please try resending in a moment.',
    'verification-cooldown': 'Please wait a moment before requesting another link.',
    'verification-failed': 'We could not send the email just now. Please try again.',
};

/**
 * The signed confirmation-waiting screen (and its confirmed state).
 *
 * The screen is the destination after signup, after a resend, and after the
 * email link itself. It deliberately offers no way into the workspace until
 * `verified` is true: the only action is a server-rate-limited resend. While it
 * waits it subscribes to the tenant's public Reverb channel and also polls its
 * own `verified` prop, so an already-open tab flips to the green check as soon
 * as the address is confirmed in another one. The poll is the fallback when
 * Reverb is not running.
 */
export default function TenantRegisterSuccess({
    domain,
    plan,
    trialDays,
    email,
    verified = false,
    verificationChannel = null,
    tenantReady = true,
    resendUrl = null,
    loginUrl = null,
    cooldownSeconds = 0,
    status = null,
}) {
    const [secondsLeft, setSecondsLeft] = useState(cooldownSeconds);
    const [sending, setSending] = useState(false);
    const [confirmed, setConfirmed] = useState(verified);

    // The prop is the server/polling answer. Once either the poll or the
    // broadcast flips the screen, a later stale response must not flip it back.
    useEffect(() => {
        if (verified) {
            setConfirmed(true);
        }
    }, [verified]);

    // The waiting screen is unauthenticated, so this is a public channel. The
    // backend derives the same HMAC name; an absent channel (builds without
    // Reverb) simply leaves the 5-second poll in charge.
    useEffect(() => {
        if (
            confirmed ||
            !verificationChannel ||
            typeof window === 'undefined' ||
            !window.Echo
        ) {
            return undefined;
        }

        const channel = window.Echo.channel(verificationChannel);

        channel.listen('.tenant.email.verified', () => {
            setConfirmed(true);
        });

        return () => {
            window.Echo?.leaveChannel?.(verificationChannel);
        };
    }, [confirmed, verificationChannel]);

    // The server is authoritative: every page load hands down the remaining
    // cooldown, and the client just ticks it down for display.
    useEffect(() => {
        setSecondsLeft(cooldownSeconds);
    }, [cooldownSeconds]);

    useEffect(() => {
        if (confirmed || secondsLeft <= 0) {
            return undefined;
        }

        const timer = window.setTimeout(() => {
            setSecondsLeft((value) => (value <= 1 ? 0 : value - 1));
        }, 1000);

        return () => window.clearTimeout(timer);
    }, [secondsLeft, confirmed]);

    useEffect(() => {
        if (confirmed) {
            return undefined;
        }

        const interval = window.setInterval(() => {
            router.reload({ only: ['verified'] });
        }, POLL_INTERVAL_MS);

        return () => window.clearInterval(interval);
    }, [confirmed]);

    const resend = () => {
        if (!resendUrl || sending || secondsLeft > 0) {
            return;
        }

        setSending(true);
        router.post(
            resendUrl,
            {},
            {
                preserveScroll: true,
                onFinish: () => setSending(false),
            }
        );
    };

    const statusMessage = status ? STATUS_MESSAGES[status] ?? null : null;

    if (confirmed) {
        return (
            <GuestLayout>
                <Head>
                    <title>Email verified</title>
                    <meta name="robots" content="noindex" />
                </Head>

                <div className="text-center">
                    <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            strokeWidth="1.5"
                            stroke="currentColor"
                            aria-hidden="true"
                            className="h-6 w-6 text-emerald-600"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M4.5 12.75l6 6 9-13.5"
                            />
                        </svg>
                    </div>

                    <p className="mt-5 text-xs font-bold uppercase tracking-[0.2em] text-brand-700">
                        Verified
                    </p>

                    <h1 className="mt-2 font-display text-3xl leading-tight tracking-tight text-ink">
                        Email verified successfully
                    </h1>

                    <div className="mt-6 rounded-xl border border-emerald-100 bg-emerald-50/60 p-5 text-center">
                        <p className="text-xs font-bold uppercase tracking-[0.14em] text-gray-600">
                            Confirmed address
                        </p>
                        <p className="mt-1 break-all font-display text-lg text-ink">{email}</p>
                    </div>

                    <p className="mt-6 text-sm text-gray-600">
                        Your email address has been confirmed. You can now sign in to your
                        workspace.
                    </p>

                    <a
                        href={loginUrl ?? '#'}
                        className="mt-6 inline-flex w-full items-center justify-center rounded-full bg-brand-700 px-6 py-3 text-sm font-semibold text-white transition ease-in-out duration-150 hover:bg-brand-800 active:bg-brand-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-ink focus-visible:ring-offset-2"
                    >
                        Go to Login
                    </a>
                </div>
            </GuestLayout>
        );
    }

    return (
        <GuestLayout>
            <Head>
                <title>Verify your email</title>
                <meta name="robots" content="noindex" />
            </Head>

            <div className="text-center">
                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        strokeWidth="1.5"
                        stroke="currentColor"
                        aria-hidden="true"
                        className="h-6 w-6 text-brand-600"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0l-9.75 6.75L2.25 6.75"
                        />
                    </svg>
                </div>

                <p className="mt-5 text-xs font-bold uppercase tracking-[0.2em] text-brand-700">
                    One last step
                </p>

                <h1 className="mt-2 font-display text-3xl leading-tight tracking-tight text-ink">
                    Check your email
                </h1>

                <p className="mt-2 text-sm text-gray-600">
                    {plan} plan{trialDays ? ` · ${trialDays}-day free trial` : ''}
                </p>

                <div className="mt-6 rounded-xl border border-brand-100 bg-brand-50/60 p-5 text-center">
                    <p className="text-xs font-bold uppercase tracking-[0.14em] text-gray-600">
                        Verification link sent to
                    </p>
                    <p className="mt-1 break-all font-display text-lg text-ink">{email}</p>
                </div>

                {statusMessage && (
                    <p
                        role="status"
                        className="mt-4 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-800"
                    >
                        {statusMessage}
                    </p>
                )}

                <p className="mt-6 text-sm text-gray-600">
                    <span className="font-medium text-ink">
                        You must confirm your email to continue.
                    </span>{' '}
                    Open the link we sent to the address above — the workspace stays locked until
                    it is confirmed.
                </p>

                <ol className="mt-6 space-y-2 rounded-xl border border-gray-200 p-5 text-left text-sm text-gray-600">
                    <li>1. Open the email from us.</li>
                    <li>2. Click “Verify my email”.</li>
                    <li>
                        3. Sign in to <span className="font-medium text-gray-900">{domain}</span>.
                    </li>
                </ol>

                {!tenantReady && (
                    <p className="mt-4 text-xs text-gray-500">
                        Your workspace is still being prepared. If the email does not arrive, try
                        resending in a moment.
                    </p>
                )}

                <button
                    type="button"
                    onClick={resend}
                    disabled={!resendUrl || sending || secondsLeft > 0}
                    className="mt-6 inline-flex items-center justify-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-ink disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {sending
                        ? 'Sending…'
                        : secondsLeft > 0
                          ? `Resend email in ${secondsLeft}s`
                          : 'Resend email'}
                </button>

                <p className="mt-4 text-xs text-gray-500">
                    Did not receive it? Use the button above to resend the link.
                </p>
            </div>
        </GuestLayout>
    );
}
