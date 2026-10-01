import Wordmark from '@/Components/BrandLogo';
import { Link } from '@inertiajs/react';

export default function Guest({ children, wide = false }) {
    return (
        <div className="relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 py-12 bg-ink">
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -right-32 -top-32 h-96 w-96 rounded-full bg-brand-500/20 blur-3xl"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -bottom-40 -left-32 h-80 w-80 rounded-full bg-brand-600/10 blur-3xl"
            />

            <div className="relative flex w-full flex-col items-center">
                <Link href="/" className="rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                    <Wordmark />
                </Link>

                <div
                    className={
                        wide
                            ? 'mt-8 w-full overflow-hidden rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-black/5 sm:p-8'
                            : 'mt-8 w-full max-w-md overflow-hidden rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-black/5 sm:p-8'
                    }
                >
                    {children}
                </div>

                <p className="mt-8 text-xs text-white/40">
                    © {new Date().getFullYear()} ShoppingLi. All rights reserved.
                </p>
            </div>
        </div>
    );
}