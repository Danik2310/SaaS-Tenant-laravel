import GuestLayout from '@/Layouts/GuestLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, Link, useForm } from '@inertiajs/react';

/**
 * The `verified` middleware parks an unverified owner here. It is the only
 * place they can resend the link, so the page has to be explicit that the
 * workspace stays locked until the address is confirmed — the stock Breeze
 * copy only asked politely and left the visitor guessing.
 */
export default function VerifyEmail({ status, email }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head>
                <title>Confirm your email</title>
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
                            d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"
                        />
                    </svg>
                </div>

                <p className="mt-5 text-xs font-bold uppercase tracking-[0.2em] text-brand-700">One last step</p>

                <h1 className="mt-2 font-display text-3xl leading-tight tracking-tight text-ink">
                    Confirm your email
                </h1>

                <div className="mt-6 rounded-xl border border-brand-100 bg-brand-50/60 p-5 text-center">
                    <p className="text-xs font-bold uppercase tracking-[0.14em] text-gray-600">
                        Verification link sent to
                    </p>
                    <p className="mt-1 break-all font-display text-lg text-ink">{email}</p>
                </div>

                <p className="mt-6 text-sm text-gray-600">
                    You must confirm your email to continue. Open the link we sent to the address
                    above — the workspace stays locked until it is confirmed, and the link expires
                    after one hour.
                </p>

                {status === 'verification-link-sent' && (
                    <p
                        role="status"
                        className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                    >
                        A new link is on its way. It can take a minute to arrive.
                    </p>
                )}

                <form onSubmit={submit} className="mt-6">
                    <PrimaryButton className="w-full" disabled={processing}>
                        Resend verification email
                    </PrimaryButton>
                </form>

                <p className="mt-4 text-xs text-gray-500">
                    Signed in as the wrong person?{' '}
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="rounded-md font-medium text-brand-700 underline hover:text-brand-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-ink"
                    >
                        Log out
                    </Link>
                    .
                </p>
            </div>
        </GuestLayout>
    );
}
