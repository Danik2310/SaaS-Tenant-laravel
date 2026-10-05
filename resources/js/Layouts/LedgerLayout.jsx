import Wordmark from '@/Components/BrandLogo';
import { Link } from '@inertiajs/react';

export default function LedgerLayout({ children }) {
    return (
        <div className="sl-marketing relative flex min-h-[100svh] flex-col bg-ink text-white">
            {/* Ruled field instead of the blurred radial blobs the guest shell
                carried. Same job — structure the flat dark plane — but it reads
                as the same drafting paper the landing page is drawn on. */}
            <div aria-hidden="true" className="sl-ledger-grid pointer-events-none absolute inset-0" />

            <header className="relative border-b border-white/10">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-5 sm:px-6 lg:px-8">
                    <Link
                        href="/"
                        className="rounded-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-ink"
                    >
                        <Wordmark />
                    </Link>

                    <Link
                        href="/"
                        className="rounded-sm text-sm font-medium text-white/60 transition-colors duration-150 ease-out hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-ink"
                    >
                        ← Back to site
                    </Link>
                </div>
            </header>

            {/* Not vertically centred: step 2 is taller than any viewport, and
                centring a block that overflows clips its top on mobile browser
                chrome, where 100vh exceeds the visible area. `svh` tracks the
                visible viewport so the first field never starts under the URL bar. */}
            <main className="relative mx-auto w-full max-w-7xl flex-1 px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
                {children}
            </main>

            <footer className="relative border-t border-white/10">
                <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <p className="text-xs text-white/50">© {new Date().getFullYear()} ShoppingLi. All rights reserved.</p>
                </div>
            </footer>
        </div>
    );
}