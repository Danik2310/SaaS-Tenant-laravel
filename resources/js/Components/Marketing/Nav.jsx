import { useState } from 'react';
import { Link } from '@inertiajs/react';
import Wordmark from '@/Components/BrandLogo';
import { NAV_LINKS } from './content';

export default function Nav({ auth }) {
    const [open, setOpen] = useState(false);

    return (
        <header className="sticky top-0 z-50 border-b border-white/10 bg-ink/80 backdrop-blur-md">
            <nav aria-label="Main" className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="#top" className="rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                    <Wordmark />
                </a>

                <div className="hidden items-center gap-8 md:flex">
                    {NAV_LINKS.map((link) => (
                        <a
                            key={link.href}
                            href={link.href}
                            className="text-sm font-medium text-white/70 transition-colors duration-150 ease-out hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded-md"
                        >
                            {link.label}
                        </a>
                    ))}
                </div>

                <div className="hidden items-center gap-3 md:flex">
                    {auth.user ? (
                        <Link
                            href={route('dashboard')}
                            className="sl-press rounded-full bg-white px-4 py-2 text-sm font-semibold text-ink transition-[color,background-color,border-color,transform] duration-150 ease-out hover:bg-orange-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                        >
                            Dashboard
                        </Link>
                    ) : (
                        <>
                            <Link
                                href={route('login')}
                                className="text-sm font-medium text-white/70 transition-colors duration-150 ease-out hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded-md"
                            >
                                Log in
                            </Link>
                            <Link
                                href={route('register.tenant')}
                                className="sl-press rounded-full bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition-[color,background-color,border-color,transform] duration-150 ease-out hover:bg-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-ink"
                            >
                                Get started
                            </Link>
                        </>
                    )}
                </div>

                <div className="md:hidden">
                    <button
                        type="button"
                        onClick={() => setOpen((value) => !value)}
                        aria-expanded={open}
                        aria-controls="mobile-menu"
                        aria-label={open ? 'Close menu' : 'Open menu'}
                        className="sl-press inline-flex h-10 w-10 items-center justify-center rounded-md text-white transition-[color,background-color,border-color,transform] duration-150 ease-out hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                    >
                        {open ? (
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" className="h-6 w-6" aria-hidden="true">
                                <path d="M6 6l12 12M18 6 6 18" />
                            </svg>
                        ) : (
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" className="h-6 w-6" aria-hidden="true">
                                <path d="M4 7h16M4 12h16M4 17h16" />
                            </svg>
                        )}
                    </button>
                </div>
            </nav>

            {open && (
                <div id="mobile-menu" className="sl-menu-in border-t border-white/10 bg-ink px-4 pb-5 pt-3 md:hidden">
                    <div className="flex flex-col gap-1">
                        {NAV_LINKS.map((link) => (
                            <a
                                key={link.href}
                                href={link.href}
                                onClick={() => setOpen(false)}
                                className="rounded-md px-2 py-2.5 text-sm font-medium text-white/80 transition-colors duration-150 ease-out hover:bg-white/10 hover:text-white"
                            >
                                {link.label}
                            </a>
                        ))}
                    </div>
                    <div className="mt-4 flex items-center gap-3">
                        {auth.user ? (
                            <Link
                                href={route('dashboard')}
                                className="sl-press flex-1 rounded-full bg-white px-4 py-2.5 text-center text-sm font-semibold text-ink transition-[color,background-color,border-color,transform] duration-150 ease-out"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={route('login')}
                                    className="sl-press flex-1 rounded-full border border-white/20 px-4 py-2.5 text-center text-sm font-medium text-white transition-[color,background-color,border-color,transform] duration-150 ease-out"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={route('register.tenant')}
                                    className="sl-press flex-1 rounded-full bg-brand-500 px-4 py-2.5 text-center text-sm font-semibold text-white transition-[color,background-color,border-color,transform] duration-150 ease-out"
                                >
                                    Get started
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            )}
        </header>
    );
}
