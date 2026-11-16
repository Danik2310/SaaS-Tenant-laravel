import GuestLayout from '@/Layouts/GuestLayout';
import WorkspaceFinder from '@/Components/Marketing/WorkspaceFinder';
import { Head, Link } from '@inertiajs/react';

/**
 * Central-domain `/login`: the front door for returning users.
 *
 * Tenant hosts keep the real login under the same URI, so `route('login')`
 * works everywhere. See WorkspaceLookupController.
 */
export default function FindWorkspace({ tenant_domain_suffix = 'sasapp' }) {
    return (
        <GuestLayout>
            <Head title="Find your workspace" />

            <div>
                <h1 className="font-display text-2xl leading-tight tracking-tight text-ink">
                    Find your workspace
                </h1>
                <p className="mt-1 text-sm text-gray-600">
                    Every business on ShoppingLi has its own workspace address. Enter yours and we
                    will take you to its login page.
                </p>
            </div>

            <WorkspaceFinder className="mt-6" suffix={tenant_domain_suffix} autoFocus />

            <p className="mt-8 border-t border-gray-200 pt-6 text-center text-sm text-gray-600">
                Don&apos;t have a workspace yet?{' '}
                <Link
                    href={route('register.tenant')}
                    className="font-medium text-brand-600 underline underline-offset-4 hover:text-brand-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500"
                >
                    Create one
                </Link>
            </p>
        </GuestLayout>
    );
}
