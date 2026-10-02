import GuestLayout from '@/Layouts/GuestLayout';
import { Head } from '@inertiajs/react';

export default function TenantRegisterSuccess({ domain, loginUrl, plan, trialDays, email }) {
    return (
        <GuestLayout>
            <Head>
                <title>Workspace created</title>
                <meta name="robots" content="noindex" />
            </Head>

            <div role="status" className="text-center">
                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        strokeWidth="1.5"
                        stroke="currentColor"
                        className="h-6 w-6 text-brand-600"
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>

                <p className="mt-5 text-xs font-bold uppercase tracking-[0.2em] text-brand-700">All set</p>

                <h1 className="mt-2 font-display text-3xl leading-tight tracking-tight text-ink">
                    Your workspace is ready
                </h1>

                <p className="mt-2 text-sm text-gray-600">
                    {plan} plan{trialDays ? ` · ${trialDays}-day free trial` : ''}
                </p>

                <div className="mt-6 rounded-xl border border-brand-100 bg-brand-50/60 p-5 text-center">
                    <p className="text-xs font-bold uppercase tracking-[0.14em] text-gray-600">
                        Your workspace address
                    </p>
                    <p className="mt-1 font-display text-2xl text-ink">{domain}</p>
                </div>

                <p className="mt-6 text-sm text-gray-600">
                    Sign in with your email <span className="font-medium text-gray-900">{email}</span> and the
                    password you chose.
                </p>

                <a
                    href={loginUrl}
                    className="mt-6 inline-flex w-full items-center justify-center rounded-full bg-brand-700 px-6 py-3 text-sm font-semibold text-white transition ease-in-out duration-150 hover:bg-brand-800 active:bg-brand-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-ink focus-visible:ring-offset-2"
                >
                    Go to your workspace
                </a>
            </div>
        </GuestLayout>
    );
}
