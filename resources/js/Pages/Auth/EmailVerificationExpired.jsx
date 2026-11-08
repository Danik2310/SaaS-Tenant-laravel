import GuestLayout from '@/Layouts/GuestLayout';
import { Head } from '@inertiajs/react';

/**
 * Shown when the signed confirmation link cannot be honoured: the signature is
 * past its 60-minute window, it was tampered with, or the account it points at
 * no longer exists. Rendering a page here — instead of letting the framework
 * answer with a bare 403 — gives the owner the one route that still works:
 * sign in, then resend from the verification screen.
 */
export default function EmailVerificationExpired({ loginUrl }) {
    return (
        <GuestLayout>
            <Head>
                <title>Confirmation link expired</title>
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
                            d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                </div>

                <p className="mt-5 text-xs font-bold uppercase tracking-[0.2em] text-brand-700">
                    Link unavailable
                </p>

                <h1 className="mt-2 font-display text-3xl leading-tight tracking-tight text-ink">
                    This confirmation link is no longer valid
                </h1>

                <p className="mt-5 text-sm text-gray-600">
                    Confirmation links expire one hour after they are sent, and each one can only be
                    used once. Sign in and request a fresh link from the verification screen.
                </p>

                <a
                    href={loginUrl}
                    className="mt-6 inline-flex w-full items-center justify-center rounded-full bg-brand-700 px-6 py-3 text-sm font-semibold text-white transition ease-in-out duration-150 hover:bg-brand-800 active:bg-brand-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-ink focus-visible:ring-offset-2"
                >
                    Sign in to request a new link
                </a>

                <p className="mt-4 text-xs text-gray-500">
                    If you already confirmed this address, signing in takes you straight to your
                    workspace.
                </p>
            </div>
        </GuestLayout>
    );
}
