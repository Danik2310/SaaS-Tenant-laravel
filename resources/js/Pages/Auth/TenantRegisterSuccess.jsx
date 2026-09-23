import GuestLayout from '@/Layouts/GuestLayout';
import { Head } from '@inertiajs/react';

export default function TenantRegisterSuccess({ domain, loginUrl, plan, trialDays, email }) {
    return (
        <GuestLayout>
            <Head title="Workspace created" />

            <div className="text-center">
                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        strokeWidth="1.5"
                        stroke="currentColor"
                        className="h-6 w-6 text-green-600"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M4.5 12.75l6 6 9-13.5"
                        />
                    </svg>
                </div>

                <h2 className="mt-4 text-xl font-semibold text-gray-900">Your workspace is ready</h2>
                <p className="mt-1 text-sm text-gray-600">
                    {plan} plan{trialDays ? ` · ${trialDays}-day free trial` : ''}
                </p>

                <div className="mt-6 rounded-lg bg-gray-50 p-4 text-center">
                    <p className="text-xs font-medium uppercase tracking-wide text-gray-500">
                        Your workspace address
                    </p>
                    <p className="mt-1 text-lg font-semibold text-gray-900">{domain}</p>
                </div>

                <p className="mt-6 text-sm text-gray-600">
                    Sign in with your email{' '}
                    <span className="font-medium text-gray-900">{email}</span> and the password you
                    chose.
                </p>

                <a
                    href={loginUrl}
                    className="mt-6 inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                >
                    Go to your workspace
                </a>
            </div>
        </GuestLayout>
    );
}