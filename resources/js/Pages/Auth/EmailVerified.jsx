import { useEffect } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, router } from '@inertiajs/react';

/** Long enough to read the confirmation, short enough not to feel stuck. */
const REDIRECT_DELAY_MS = 3000;

/**
 * Result page for the signed confirmation link. It is a short stop, not a
 * destination: the green check tells the owner the address is confirmed and the
 * page hands them to the tenant login screen automatically. The fallback link
 * covers a blocked or delayed client-side redirect.
 */
export default function EmailVerified({ email, alreadyVerified, loginUrl }) {
    useEffect(() => {
        const timer = window.setTimeout(() => {
            router.visit(loginUrl, { replace: true });
        }, REDIRECT_DELAY_MS);

        return () => window.clearTimeout(timer);
    }, [loginUrl]);

    return (
        <GuestLayout>
            <Head>
                <title>{alreadyVerified ? 'Email already confirmed' : 'Email confirmed'}</title>
                <meta name="robots" content="noindex" />
            </Head>

            <div role="status" className="text-center">
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
                        <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>

                <p className="mt-5 text-xs font-bold uppercase tracking-[0.2em] text-brand-700">
                    {alreadyVerified ? 'Already confirmed' : 'You are confirmed'}
                </p>

                <h1 className="mt-2 font-display text-3xl leading-tight tracking-tight text-ink">
                    {alreadyVerified ? 'Email already confirmed' : 'Email confirmed'}
                </h1>

                <div className="mt-6 rounded-xl border border-emerald-100 bg-emerald-50/60 p-5 text-center">
                    <p className="text-xs font-bold uppercase tracking-[0.14em] text-gray-600">
                        Confirmed address
                    </p>
                    <p className="mt-1 break-all font-display text-lg text-ink">{email}</p>
                </div>

                <p className="mt-6 text-sm text-gray-600">
                    {alreadyVerified
                        ? 'This address was confirmed on an earlier visit. Taking you to the sign-in page…'
                        : 'Your workspace is unlocked. Taking you to the sign-in page…'}
                </p>

                <p className="mt-4 text-xs text-gray-500">
                    Not redirected?{' '}
                    <a
                        href={loginUrl}
                        className="rounded-md font-medium text-brand-700 underline hover:text-brand-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-ink"
                    >
                        Sign in now
                    </a>
                    .
                </p>
            </div>
        </GuestLayout>
    );
}
