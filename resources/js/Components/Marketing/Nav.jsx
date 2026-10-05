import { useEffect, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import Wordmark from '@/Components/BrandLogo';
import { NAV_LINKS } from './content';

export default function Nav({ auth }) {
    const [open, setOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);
    const [activeHref, setActiveHref] = useState(null);
    const sentinelRef = useRef(null);

    // A 1px sentinel pinned to the top of the document, cancelled out with a
    // -1px bottom margin so it costs no layout height. Observing it gives us
    // "have we left the top" without a scroll listener.
    useEffect(() => {
        const el = sentinelRef.current;

        if (!el) {
            return undefined;
        }

        const observer = new IntersectionObserver(([entry]) => setScrolled(!entry.isIntersecting), {
            threshold: 0,
        });

        observer.observe(el);

        return () => observer.disconnect();
    }, []);

    // Active section: a narrow band just below the sticky header decides which
    // anchor is current. Sections are matched against NAV_LINKS order so the
    // topmost one wins when the band overlaps two.
    useEffect(() => {
        const sections = NAV_LINKS.map((link) => document.querySelector(link.href)).filter(Boolean);

        if (sections.length === 0) {
            return undefined;
        }

        const visible = new Set();

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        visible.add(entry.target.id);
                    } else {
                        visible.delete(entry.target.id);
                    }
                });

                const match = NAV_LINKS.find((link) => visible.has(link.href.slice(1)));
                setActiveHref(match ? match.href : null);
            },
            { rootMargin: '-96px 0px -70% 0px', threshold: 0 }
        );

        sections.forEach((section) => observer.observe(section));

        return () => observer.disconnect();
    }, []);

    return (
        <>
            <div ref={sentinelRef} aria-hidden="true" className="-mb-px h-px w-full" />

            <header
                className={`sticky top-0 z-50 border-b backdrop-blur-md transition-colors duration-300 ease-out ${
                    scrolled ? 'border-white/20 bg-ink/95' : 'border-white/10 bg-ink/80'
                }`}
            >
                <nav aria-label="Main" className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <a href="#top" className="rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                        <Wordmark />
                    </a>

                    <div className="hidden items-center gap-8 md:flex">
                        {NAV_LINKS.map((link) => {
                            const active = activeHref === link.href;

                            return (
                                <a
                                    key={link.href}
                                    href={link.href}
                                    aria-current={active ? 'true' : undefined}
                                    className={`relative rounded-md text-sm font-medium transition-colors duration-150 ease-out focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 ${
                                        active ? 'text-white' : 'text-white/70 hover:text-white'
                                    }`}
                                >
                                    {link.label}
                                    <span
                                        aria-hidden="true"
                                        className={`absolute -bottom-1 left-0 h-px w-full origin-left bg-brand-500 transition-transform duration-300 ease-out ${
                                            active ? 'scale-x-100' : 'scale-x-0'
                                        }`}
                                    />
                                </a>
                            );
                        })}
                    </div>

                    <div className="hidden items-center gap-3 md:flex">
                        {auth.user ? (
                            <Link
                                href={route('dashboard')}
                                className="sl-press rounded-md bg-white px-4 py-2 text-sm font-semibold text-ink transition-[color,background-color,border-color,transform] duration-150 ease-out hover:bg-orange-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={route('login')}
                                    className="rounded-md text-sm font-medium text-white/70 transition-colors duration-150 ease-out hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={route('register.tenant')}
                                    className="sl-press rounded-md bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition-[color,background-color,border-color,transform] duration-150 ease-out hover:bg-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-ink"
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
                            {NAV_LINKS.map((link, index) => (
                                <a
                                    key={link.href}
                                    href={link.href}
                                    onClick={() => setOpen(false)}
                                    className="sl-menu-item rounded-md px-2 py-2.5 text-sm font-medium text-white/80 transition-colors duration-150 ease-out hover:bg-white/10 hover:text-white"
                                    style={{ animationDelay: `${index * 40}ms` }}
                                >
                                    {link.label}
                                </a>
                            ))}
                        </div>
                        <div className="mt-4 flex items-center gap-3">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="sl-press flex-1 rounded-md bg-white px-4 py-2.5 text-center text-sm font-semibold text-ink transition-[color,background-color,border-color,transform] duration-150 ease-out"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="sl-press flex-1 rounded-md border border-white/20 px-4 py-2.5 text-center text-sm font-medium text-white transition-[color,background-color,border-color,transform] duration-150 ease-out"
                                    >
                                        Log in
                                    </Link>
                                    <Link
                                        href={route('register.tenant')}
                                        className="sl-press flex-1 rounded-md bg-brand-500 px-4 py-2.5 text-center text-sm font-semibold text-white transition-[color,background-color,border-color,transform] duration-150 ease-out"
                                    >
                                        Get started
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                )}
            </header>
        </>
    );
}