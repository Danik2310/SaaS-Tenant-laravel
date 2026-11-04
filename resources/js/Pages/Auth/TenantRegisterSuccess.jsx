import GuestLayout from '@/Layouts/GuestLayout';
import { Head } from '@inertiajs/react';

export default function TenantRegisterSuccess({ domain, loginUrl, plan, trialDays, email }) {
    return (
        <GuestLayout>
            <Head>
                <title>Verify your email</title>
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
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0l-9.75 6.75L2.25 6.75"
                        />
                    </svg>
                </div>

                <p className="mt-5 text-xs font-bold uppercase tracking-[0.2em] text-brand-700">One last step</p>

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

                <p className="mt-6 text-sm text-gray-600">
                    Open that link to activate your workspace. You will be asked to sign in with the
                    password you chose.
                </p>

                <ol className="mt-6 space-y-2 rounded-xl border border-gray-200 p-5 text-left text-sm text-gray-600">
                    <li>1. Open the email from us.</li>
                    <li>2. Click “Verify my email”.</li>
                    <li>
                        3. Sign in to <span className="font-medium text-gray-900">{domain}</span>.
                    </li>
                </ol>

                <a
                    href={loginUrl}
                    className="mt-6 inline-flex w-full items-center justify-center rounded-full bg-brand-700 px-6 py-3 text-sm font-semibold text-white transition ease-in-out duration-150 hover:bg-brand-800 active:bg-brand-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-ink focus-visible:ring-offset-2"
                >
                    Go to your workspace
                </a>

                <p className="mt-4 text-xs text-gray-500">
                    Did not receive it? Sign in and resend the link from the verification screen.
                </p>
            </div>
        </GuestLayout>
    );
}
