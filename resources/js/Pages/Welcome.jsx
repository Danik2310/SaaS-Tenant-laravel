import { useEffect, useRef, useState } from 'react';
import { Link, Head } from '@inertiajs/react';
import Wordmark from '@/Components/BrandLogo';

/* --------------------------------------------------------------------------
   Motion helpers
-------------------------------------------------------------------------- */

function usePrefersReducedMotion() {
    const [reduced, setReduced] = useState(() =>
        typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches ? true : false
    );

    useEffect(() => {
        const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
        const onChange = (event) => setReduced(event.matches);
        mq.addEventListener('change', onChange);

        return () => mq.removeEventListener('change', onChange);
    }, []);

    return reduced;
}

function Reveal({ children, className = '', delay = 0, as: Tag = 'div' }) {
    const ref = useRef(null);
    const reduced = usePrefersReducedMotion();

    useEffect(() => {
        const el = ref.current;

        if (!el || reduced) {
            return undefined;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        el.classList.add('is-visible');
                        observer.disconnect();
                    }
                });
            },
            { threshold: 0.15, rootMargin: '0px 0px -48px 0px' }
        );

        observer.observe(el);

        return () => observer.disconnect();
    }, [reduced]);

    if (reduced) {
        return <Tag className={className}>{children}</Tag>;
    }

    return (
        <Tag
            ref={ref}
            className={`reveal ${className}`}
            style={delay ? { transitionDelay: `${delay}ms` } : undefined}
        >
            {children}
        </Tag>
    );
}

/* --------------------------------------------------------------------------
   Icons (inline, stroke-based)
-------------------------------------------------------------------------- */

const iconProps = {
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.6,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
};

function BoxIcon({ className = 'h-6 w-6' }) {
    return (
        <svg viewBox="0 0 24 24" {...iconProps} className={className} aria-hidden="true">
            <path d="M20 7.5 12 3 4 7.5v9L12 21l8-4.5v-9Z" />
            <path d="M4.5 7.6 12 12l7.5-4.4" />
            <path d="M12 12v9" />
        </svg>
    );
}

function TagIcon({ className = 'h-6 w-6' }) {
    return (
        <svg viewBox="0 0 24 24" {...iconProps} className={className} aria-hidden="true">
            <path d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
            <path d="M6 6h.008v.008H6V6Z" />
        </svg>
    );
}

function MapPinIcon({ className = 'h-6 w-6' }) {
    return (
        <svg viewBox="0 0 24 24" {...iconProps} className={className} aria-hidden="true">
            <path d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            <path d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
        </svg>
    );
}

function ArrowsIcon({ className = 'h-6 w-6' }) {
    return (
        <svg viewBox="0 0 24 24" {...iconProps} className={className} aria-hidden="true">
            <path d="M8 7h12m0 0-3-3m3 3-3 3" />
            <path d="M16 17H4m0 0 3 3m-3-3 3-3" />
        </svg>
    );
}

function ChartIcon({ className = 'h-6 w-6' }) {
    return (
        <svg viewBox="0 0 24 24" {...iconProps} className={className} aria-hidden="true">
            <path d="M4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75C3 12.504 3.504 12 4.125 12Z" />
            <path d="M9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625Z" />
            <path d="M16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
        </svg>
    );
}

function ShieldIcon({ className = 'h-6 w-6' }) {
    return (
        <svg viewBox="0 0 24 24" {...iconProps} className={className} aria-hidden="true">
            <path d="M12 3 5 6.5v5c0 4.2 2.9 7.6 7 9.5 4.1-1.9 7-5.3 7-9.5v-5L12 3Z" />
            <path d="m9 11.5 2 2 4-4" />
        </svg>
    );
}

function TableIcon({ className = 'h-7 w-7' }) {
    return (
        <svg viewBox="0 0 24 24" {...iconProps} className={className} aria-hidden="true">
            <path d="M3 5.25A2.25 2.25 0 0 1 5.25 3h13.5A2.25 2.25 0 0 1 21 5.25v13.5A2.25 2.25 0 0 1 18.75 21H5.25A2.25 2.25 0 0 1 3 18.75V5.25Z" />
            <path d="M3 9.5h18M9.5 9.5V21" />
        </svg>
    );
}

function PulseIcon({ className = 'h-7 w-7' }) {
    return (
        <svg viewBox="0 0 24 24" {...iconProps} className={className} aria-hidden="true">
            <path d="M2 12h4l2.5-7 4.5 14 2.5-7h6.5" />
        </svg>
    );
}

/* --------------------------------------------------------------------------
   Content data
-------------------------------------------------------------------------- */

const NAV_LINKS = [
    { href: '#features', label: 'Features' },
    { href: '#how-it-works', label: 'How it works' },
    { href: '#pricing', label: 'Pricing' },
    { href: '#faq', label: 'FAQ' },
];

const CAPABILITIES = ['Products', 'Categories', 'Warehouses', 'Stock movements', 'Orders', 'Payments'];

const PAINS = [
    {
        icon: TableIcon,
        title: 'Replace the spreadsheet',
        body: 'Your catalog, warehouses and movements in one source of truth. No more duplicated sheets, no more “we’ll fix it later” rows.',
    },
    {
        icon: PulseIcon,
        title: 'Track stock in real time',
        body: 'Every product, every location, always current. See what you actually have before you promise it to someone.',
    },
    {
        icon: MapPinIcon,
        title: 'Run multiple warehouses',
        body: 'Spread inventory across locations and keep a single, honest view of everything — from one dashboard.',
    },
];

const FEATURES = [
    {
        icon: BoxIcon,
        title: 'One catalog, always up to date',
        body: 'Products with categories and prices in a single place. Update once, and every shelf sees it.',
    },
    {
        icon: TagIcon,
        title: 'Your catalog, your way',
        body: 'Structure categories the way your shop actually thinks, so the right product is always easy to find.',
    },
    {
        icon: MapPinIcon,
        title: 'Know what’s where',
        body: 'Run multiple warehouses from one dashboard — quantities and locations at a glance.',
    },
    {
        icon: ArrowsIcon,
        title: 'Every movement, recorded',
        body: 'Stock in, stock out — every movement leaves a trace you can follow. Available on Growth plans and up.',
    },
    {
        icon: ChartIcon,
        title: 'Numbers you can act on',
        body: 'A dashboard that shows what you hold, what moved, and what’s selling — in real time.',
    },
    {
        icon: ShieldIcon,
        title: 'Private by design',
        body: 'Each business runs in its own secured workspace. Your data stays yours, full stop.',
    },
];

const STEPS = [
    {
        n: '01',
        title: 'Create your workspace',
        body: 'Pick a plan, choose your address, and you’re live in minutes. The trial needs no card.',
    },
    {
        n: '02',
        title: 'Add your catalog',
        body: 'Bring in products, add categories, set up your warehouses. Start as simple as you like.',
    },
    {
        n: '03',
        title: 'Track and grow',
        body: 'Record movements, watch your dashboard, and upgrade when the shop is ready.',
    },
];

const PLANS = [
    {
        name: 'Free',
        price: '$0',
        period: 'free forever',
        note: 'One shop, fully organized.',
        features: ['Products & categories', '1 warehouse', 'Dashboard overview'],
        highlight: false,
        cta: 'Start free',
    },
    {
        name: 'Growth',
        price: '$15',
        period: '/mo',
        note: 'For shops that track movements.',
        features: ['Everything in Free', 'Inventory movements', 'More users & warehouses'],
        highlight: true,
        cta: 'Start 14-day trial',
    },
    {
        name: 'Pro',
        price: '$29',
        period: '/mo',
        note: 'For growing, multi-location shops.',
        features: ['Everything in Growth', 'Higher storage & limits', 'Multi-warehouse visibility'],
        highlight: false,
        cta: 'Start 14-day trial',
    },
    {
        name: 'Enterprise',
        price: '$99',
        period: '/mo',
        note: 'For teams at full scale.',
        features: ['Everything in Pro', 'Top limits & storage', 'Priority support'],
        highlight: false,
        cta: 'Start 14-day trial',
    },
];

const FAQS = [
    {
        q: 'Is it really free?',
        a: 'Yes — the Free plan costs nothing, forever. Paid plans begin with a 14-day free trial, and nothing is charged during the trial.',
    },
    {
        q: 'Can I try without a card?',
        a: 'Absolutely. Trials and the Free plan have no charge; payment is only entered at checkout if you pick a paid plan.',
    },
    {
        q: 'Can I run several warehouses?',
        a: 'Yes. Warehouse support scales with your plan, from a single location on Free up to full multi-warehouse tracking.',
    },
    {
        q: 'What happens if I hit a limit?',
        a: 'Your workspace stays fully online. To grow beyond a plan’s users, products, warehouses or storage, simply upgrade.',
    },
    {
        q: 'How is my data kept private?',
        a: 'Every business runs in its own isolated workspace with its own database and access rules — built multi-tenant from the ground up.',
    },
];

const STOCK_ROWS = [
    { name: 'Wireless earbuds', category: 'Audio', qty: '342', delta: '+18 in', positive: true },
    { name: 'Canvas tote', category: 'Bags', qty: '96', delta: '−12 out', positive: false },
    { name: 'Linen throw', category: 'Home', qty: '204', delta: '+7 in', positive: true },
];

/* --------------------------------------------------------------------------
   Sections
-------------------------------------------------------------------------- */

function Nav({ auth }) {
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
                            className="text-sm font-medium text-white/70 transition-colors duration-150 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded-md"
                        >
                            {link.label}
                        </a>
                    ))}
                </div>

                <div className="hidden items-center gap-3 md:flex">
                    {auth.user ? (
                        <Link
                            href={route('dashboard')}
                            className="rounded-full bg-white px-4 py-2 text-sm font-semibold text-ink transition-colors duration-150 hover:bg-orange-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                        >
                            Dashboard
                        </Link>
                    ) : (
                        <>
                            <Link
                                href={route('login')}
                                className="text-sm font-medium text-white/70 transition-colors duration-150 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded-md"
                            >
                                Log in
                            </Link>
                            <Link
                                href={route('register.tenant')}
                                className="rounded-full bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition-colors duration-150 hover:bg-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-ink"
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
                        className="inline-flex h-10 w-10 items-center justify-center rounded-md text-white transition-colors duration-150 hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
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
                <div id="mobile-menu" className="border-t border-white/10 bg-ink px-4 pb-5 pt-3 md:hidden">
                    <div className="flex flex-col gap-1">
                        {NAV_LINKS.map((link) => (
                            <a
                                key={link.href}
                                href={link.href}
                                onClick={() => setOpen(false)}
                                className="rounded-md px-2 py-2.5 text-sm font-medium text-white/80 transition-colors duration-150 hover:bg-white/10 hover:text-white"
                            >
                                {link.label}
                            </a>
                        ))}
                    </div>
                    <div className="mt-4 flex items-center gap-3">
                        {auth.user ? (
                            <Link
                                href={route('dashboard')}
                                className="flex-1 rounded-full bg-white px-4 py-2.5 text-center text-sm font-semibold text-ink"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={route('login')}
                                    className="flex-1 rounded-full border border-white/20 px-4 py-2.5 text-center text-sm font-medium text-white"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={route('register.tenant')}
                                    className="flex-1 rounded-full bg-brand-500 px-4 py-2.5 text-center text-sm font-semibold text-white"
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

function Hero({ auth }) {
    return (
        <section id="top" className="relative overflow-hidden bg-ink text-white">
            <div aria-hidden="true" className="pointer-events-none absolute -right-32 -top-32 h-96 w-96 rounded-full bg-brand-500/20 blur-3xl" />
            <div aria-hidden="true" className="pointer-events-none absolute -bottom-40 left-1/3 h-80 w-80 rounded-full bg-brand-600/10 blur-3xl" />

            <div className="relative mx-auto grid max-w-7xl items-center gap-14 px-4 pb-24 pt-20 sm:px-6 lg:grid-cols-2 lg:px-8 lg:pb-32 lg:pt-28">
                <div>
                    <Reveal>
                        <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-400">
                            Inventory &amp; product management for your shop
                        </p>
                    </Reveal>

                    <Reveal delay={80}>
                        <h1 className="mt-5 font-display text-4xl leading-[1.05] tracking-tight sm:text-5xl lg:text-6xl">
                            Inventory that keeps up with your business.
                        </h1>
                    </Reveal>

                    <Reveal delay={160}>
                        <p className="mt-6 max-w-xl text-lg leading-relaxed text-white/70">
                            ShoppingLi brings your products, warehouses and stock into one clean workspace — no
                            spreadsheets, no guesswork.
                        </p>
                    </Reveal>

                    <Reveal delay={240}>
                        <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                            <Link
                                href={route('register.tenant')}
                                className="rounded-full bg-brand-500 px-6 py-3 text-center text-sm font-semibold text-white transition-all duration-300 ease-out hover:scale-[1.02] hover:bg-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-ink"
                            >
                                Start free — no card required
                            </Link>
                            <a
                                href="#pricing"
                                className="rounded-full border border-white/20 px-6 py-3 text-center text-sm font-semibold text-white transition-colors duration-300 ease-out hover:border-white/50 hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                            >
                                See pricing
                            </a>
                        </div>
                    </Reveal>

                    <Reveal delay={320}>
                        <ul className="mt-8 flex flex-wrap gap-x-5 gap-y-2 text-sm text-white/50">
                            <li className="flex items-center gap-2">
                                <span className="h-1.5 w-1.5 rounded-full bg-brand-500" aria-hidden="true" />
                                14-day free trial
                            </li>
                            <li className="flex items-center gap-2">
                                <span className="h-1.5 w-1.5 rounded-full bg-brand-500" aria-hidden="true" />
                                Up in minutes
                            </li>
                            <li className="flex items-center gap-2">
                                <span className="h-1.5 w-1.5 rounded-full bg-brand-500" aria-hidden="true" />
                                One secure workspace per business
                            </li>
                        </ul>
                    </Reveal>
                </div>

                <Reveal delay={200} className="relative">
                    <div className="relative rounded-2xl border border-white/10 bg-white/[0.04] p-5 shadow-2xl shadow-black/50 backdrop-blur-sm sm:p-6">
                        <div className="flex items-center justify-between">
                            <p className="text-sm font-semibold text-white">North warehouse</p>
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-brand-500/15 px-2.5 py-1 text-xs font-medium text-brand-300">
                                <span className="h-1.5 w-1.5 rounded-full bg-brand-400" aria-hidden="true" />
                                Live
                            </span>
                        </div>

                        <div className="mt-5 space-y-3">
                            {STOCK_ROWS.map((row) => (
                                <div
                                    key={row.name}
                                    className="flex items-center justify-between gap-4 rounded-xl border border-white/5 bg-white/[0.03] px-4 py-3"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium text-white">{row.name}</p>
                                        <p className="text-xs text-white/40">{row.category}</p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-3">
                                        <span className="font-display text-base text-white">{row.qty}</span>
                                        <span
                                            className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                                row.positive
                                                    ? 'bg-white/10 text-white/70'
                                                    : 'bg-brand-500/15 text-brand-300'
                                            }`}
                                        >
                                            {row.delta}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="mt-5 border-t border-white/10 pt-4">
                            <div className="flex items-center justify-between text-xs">
                                <span className="text-white/40">Total SKUs</span>
                                <span className="font-semibold text-white">1,284</span>
                            </div>
                            <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10" aria-hidden="true">
                                <div className="h-full w-2/3 rounded-full bg-brand-500" />
                            </div>
                        </div>
                    </div>

                    <div
                        aria-hidden="true"
                        className="absolute -left-6 -top-6 -z-10 h-28 w-28 rounded-2xl bg-brand-600/30 blur-2xl"
                    />
                </Reveal>
            </div>
        </section>
    );
}

function CapabilityStrip() {
    return (
        <section aria-label="What ShoppingLi manages" className="border-t border-white/5 bg-ink pb-14 text-white">
            <Reveal>
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <p className="text-center text-xs font-bold uppercase tracking-[0.2em] text-white/40">
                        Made for the way shops really run
                    </p>
                    <ul className="mt-6 flex flex-wrap items-center justify-center gap-3">
                        {CAPABILITIES.map((item) => (
                            <li
                                key={item}
                                className="rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-sm font-medium text-white/80 transition-colors duration-300 ease-out hover:border-brand-500/50 hover:text-white"
                            >
                                {item}
                            </li>
                        ))}
                    </ul>
                </div>
            </Reveal>
        </section>
    );
}

function ProblemSolution() {
    return (
        <section className="bg-white py-24 text-ink">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <Reveal>
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-600">Why ShoppingLi</p>
                    <h2 className="mt-4 max-w-2xl font-display text-3xl leading-tight tracking-tight sm:text-4xl">
                        Spreadsheets were never built for inventory.
                    </h2>
                    <p className="mt-5 max-w-2xl text-lg leading-relaxed text-gray-600">
                        When products live in a spreadsheet and stock lives somewhere else, nothing quite adds up.
                        ShoppingLi brings it all into one place — so you can trust the numbers at a glance.
                    </p>
                </Reveal>

                <div className="mt-14 grid gap-8 md:grid-cols-3">
                    {PAINS.map((item, index) => (
                        <Reveal key={item.title} delay={index * 100} className="h-full">
                            <div className="h-full rounded-2xl border border-gray-200 bg-white p-7 transition-all duration-300 ease-out hover:-translate-y-1 hover:border-brand-300 hover:shadow-lg hover:shadow-brand-500/10">
                                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-ink text-brand-400">
                                    <item.icon />
                                </div>
                                <h3 className="mt-5 text-lg font-bold">{item.title}</h3>
                                <p className="mt-2 leading-relaxed text-gray-600">{item.body}</p>
                            </div>
                        </Reveal>
                    ))}
                </div>
            </div>
        </section>
    );
}

function Features() {
    return (
        <section id="features" className="scroll-mt-24 bg-gray-50 py-24 text-ink">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <Reveal>
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-600">Everything in one place</p>
                    <div className="mt-4 flex flex-col justify-between gap-6 md:flex-row md:items-end">
                        <h2 className="max-w-xl font-display text-3xl leading-tight tracking-tight sm:text-4xl">
                            Built for the daily grind of running a shop.
                        </h2>
                        <p className="max-w-sm text-base leading-relaxed text-gray-600">
                            Everything you already juggle — products, categories, warehouses, movements — in a
                            workspace that behaves like one.
                        </p>
                    </div>
                </Reveal>

                <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {FEATURES.map((item, index) => (
                        <Reveal key={item.title} delay={(index % 3) * 90} className="h-full">
                            <article className="group h-full rounded-2xl border border-gray-200 bg-white p-7 transition-all duration-300 ease-out hover:-translate-y-1 hover:border-brand-300 hover:shadow-lg hover:shadow-brand-500/10">
                                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 transition-colors duration-300 ease-out group-hover:bg-brand-500 group-hover:text-white">
                                    <item.icon />
                                </div>
                                <h3 className="mt-5 text-lg font-bold">{item.title}</h3>
                                <p className="mt-2 leading-relaxed text-gray-600">{item.body}</p>
                            </article>
                        </Reveal>
                    ))}
                </div>
            </div>
        </section>
    );
}

function HowItWorks() {
    return (
        <section id="how-it-works" className="scroll-mt-24 bg-white py-24 text-ink">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <Reveal>
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-600">How it works</p>
                    <h2 className="mt-4 max-w-2xl font-display text-3xl leading-tight tracking-tight sm:text-4xl">
                        From spreadsheet chaos to order — in three steps.
                    </h2>
                </Reveal>

                <ol className="mt-14 grid gap-10 md:grid-cols-3">
                    {STEPS.map((step, index) => (
                        <Reveal key={step.n} as="li" delay={index * 120}>
                            <div className="relative">
                                <span className="font-display text-5xl text-brand-500">{step.n}</span>
                                <h3 className="mt-4 text-lg font-bold">{step.title}</h3>
                                <p className="mt-2 leading-relaxed text-gray-600">{step.body}</p>
                                <div
                                    aria-hidden="true"
                                    className="mt-6 h-px w-full bg-gradient-to-r from-brand-500/60 to-transparent md:hidden"
                                />
                            </div>
                        </Reveal>
                    ))}
                </ol>
            </div>
        </section>
    );
}

function Pricing() {
    return (
        <section id="pricing" className="scroll-mt-24 bg-gray-50 py-24 text-ink">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <Reveal>
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-600">Pricing</p>
                    <div className="mt-4 flex flex-col justify-between gap-6 md:flex-row md:items-end">
                        <div>
                            <h2 className="max-w-2xl font-display text-3xl leading-tight tracking-tight sm:text-4xl">
                                Start free. Upgrade when the shop grows.
                            </h2>
                            <p className="mt-5 max-w-2xl text-base leading-relaxed text-gray-600">
                                Every paid plan begins with a 14-day free trial. Inventory movements unlock on Growth and
                                up.
                            </p>
                        </div>
                        <Link
                            href={route('register.tenant')}
                            className="shrink-0 rounded-full border border-ink px-6 py-3 text-center text-sm font-semibold text-ink transition-colors duration-300 ease-out hover:bg-ink hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                        >
                            Compare details at signup
                        </Link>
                    </div>
                </Reveal>

                <div className="mt-14 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    {PLANS.map((plan, index) => (
                        <Reveal key={plan.name} delay={index * 80} className="h-full">
                            <article
                                className={`relative flex h-full flex-col rounded-2xl border p-7 transition-all duration-300 ease-out hover:-translate-y-1 ${
                                    plan.highlight
                                        ? 'border-brand-500 bg-ink text-white shadow-xl shadow-brand-500/20'
                                        : 'border-gray-200 bg-white hover:border-brand-300 hover:shadow-lg hover:shadow-brand-500/10'
                                }`}
                            >
                                {plan.highlight && (
                                    <span className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-brand-500 px-3 py-1 text-xs font-bold uppercase tracking-wider text-white">
                                        Most popular
                                    </span>
                                )}

                                <h3 className={`text-sm font-bold uppercase tracking-wider ${plan.highlight ? 'text-brand-400' : 'text-gray-500'}`}>
                                    {plan.name}
                                </h3>

                                <div className="mt-4 flex items-baseline gap-1">
                                    <span className="font-display text-4xl">{plan.price}</span>
                                    <span className={`text-sm ${plan.highlight ? 'text-white/60' : 'text-gray-500'}`}>
                                        {plan.period}
                                    </span>
                                </div>

                                <p className={`mt-1 text-sm ${plan.highlight ? 'text-white/60' : 'text-gray-500'}`}>
                                    {plan.note}
                                </p>

                                <ul className={`mt-5 space-y-2.5 text-sm ${plan.highlight ? 'text-white/80' : 'text-gray-700'}`}>
                                    {plan.features.map((feature) => (
                                        <li key={feature} className="flex items-start gap-2.5">
                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke={plan.highlight ? '#FB923C' : '#F97316'}
                                                strokeWidth="2.4"
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                className="mt-0.5 h-4 w-4 shrink-0"
                                                aria-hidden="true"
                                            >
                                                <path d="m5 12.5 4.5 4.5L19 7" />
                                            </svg>
                                            {feature}
                                        </li>
                                    ))}
                                </ul>

                                <div className="mt-auto pt-7">
                                    <Link
                                        href={route('register.tenant')}
                                        className={`w-full rounded-full px-5 py-2.5 text-center text-sm font-semibold transition-colors duration-300 ease-out focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 ${
                                            plan.highlight
                                                ? 'bg-brand-500 text-white hover:bg-brand-600 focus-visible:ring-brand-500 focus-visible:ring-offset-ink'
                                                : 'border border-ink bg-transparent text-ink hover:bg-ink hover:text-white focus-visible:ring-brand-500'
                                        }`}
                                    >
                                        {plan.cta}
                                    </Link>
                                </div>

                                <p className={`mt-4 text-center text-xs ${plan.highlight ? 'text-white/50' : 'text-gray-400'}`}>
                                    Plans differ in users, warehouses, products, categories and storage.
                                </p>
                            </article>
                        </Reveal>
                    ))}
                </div>
            </div>
        </section>
    );
}

function Testimonial() {
    return (
        <section className="bg-ink py-24 text-white">
            <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <Reveal>
                    <blockquote className="text-center">
                        <p aria-hidden="true" className="font-display text-6xl leading-none text-brand-500">
                            “
                        </p>
                        <p className="-mt-6 text-2xl font-medium leading-relaxed sm:text-3xl sm:leading-snug">
                            We stopped reconciling three spreadsheets every Friday. ShoppingLi just shows us the truth —
                            what we have, where it is, and what moved.
                        </p>
                        <footer className="mt-8 text-sm font-semibold uppercase tracking-wider text-white/50">
                            Store manager — small retail
                        </footer>
                    </blockquote>
                </Reveal>
            </div>
        </section>
    );
}

function Faq() {
    const [openIndex, setOpenIndex] = useState(0);

    return (
        <section id="faq" className="scroll-mt-24 bg-white py-24 text-ink">
            <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <Reveal>
                    <p className="text-center text-xs font-bold uppercase tracking-[0.2em] text-brand-600">FAQ</p>
                    <h2 className="mt-4 text-center font-display text-3xl leading-tight tracking-tight sm:text-4xl">
                        Questions, answered.
                    </h2>
                </Reveal>

                <div className="mt-12 divide-y divide-gray-200">
                    {FAQS.map((faq, index) => {
                        const open = openIndex === index;

                        return (
                            <Reveal key={faq.q} delay={index * 60}>
                                <div>
                                    <button
                                        type="button"
                                        onClick={() => setOpenIndex(open ? -1 : index)}
                                        aria-expanded={open}
                                        aria-controls={`faq-panel-${index}`}
                                        className="flex w-full items-center justify-between gap-6 py-5 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded-md"
                                    >
                                        <span className="text-base font-bold sm:text-lg">{faq.q}</span>
                                        <span
                                            aria-hidden="true"
                                            className={`h-6 w-6 shrink-0 text-brand-500 transition-transform duration-300 ease-out ${
                                                open ? 'rotate-45' : ''
                                            }`}
                                        >
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" className="h-6 w-6">
                                                <path d="M12 5v14M5 12h14" />
                                            </svg>
                                        </span>
                                    </button>
                                    <div
                                        id={`faq-panel-${index}`}
                                        role="region"
                                        hidden={!open}
                                        className="pb-5 pr-10 leading-relaxed text-gray-600"
                                    >
                                        {faq.a}
                                    </div>
                                </div>
                            </Reveal>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}

function FinalCta() {
    return (
        <section className="relative overflow-hidden bg-ink py-24 text-white">
            <div aria-hidden="true" className="pointer-events-none absolute -left-24 top-1/2 h-72 w-72 -translate-y-1/2 rounded-full bg-brand-500/20 blur-3xl" />
            <div className="relative mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
                <Reveal>
                    <h2 className="font-display text-3xl leading-tight tracking-tight sm:text-5xl">
                        Ready to get organized?
                    </h2>
                    <p className="mt-5 text-lg text-white/70">
                        Create your workspace — it takes minutes. No card until you choose a paid plan.
                    </p>
                    <div className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <Link
                            href={route('register.tenant')}
                            className="w-full rounded-full bg-brand-500 px-8 py-3.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:scale-[1.02] hover:bg-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-ink sm:w-auto"
                        >
                            Start free
                        </Link>
                        <Link
                            href={route('login')}
                            className="w-full rounded-full border border-white/20 px-8 py-3.5 text-sm font-semibold text-white transition-colors duration-300 ease-out hover:border-white/50 hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 sm:w-auto"
                        >
                            Log in
                        </Link>
                    </div>
                </Reveal>
            </div>
        </section>
    );
}

function Footer() {
    return (
        <footer className="border-t border-white/10 bg-ink py-14 text-white">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex flex-col items-start justify-between gap-10 md:flex-row md:items-center">
                    <div>
                        <Wordmark />
                        <p className="mt-3 text-sm text-white/50">Run your whole shop from one line.</p>
                    </div>

                    <nav aria-label="Footer" className="flex flex-wrap gap-x-8 gap-y-3">
                        {NAV_LINKS.map((link) => (
                            <a
                                key={link.href}
                                href={link.href}
                                className="text-sm text-white/70 transition-colors duration-150 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded-md"
                            >
                                {link.label}
                            </a>
                        ))}
                    </nav>
                </div>

                <div className="mt-12 flex flex-col items-start justify-between gap-4 border-t border-white/10 pt-8 text-sm text-white/40 md:flex-row md:items-center">
                    <p>© {new Date().getFullYear()} ShoppingLi. All rights reserved.</p>
                    <div className="flex gap-6">
                        <span>Privacy</span>
                        <span>Terms</span>
                    </div>
                </div>
            </div>
        </footer>
    );
}

/* --------------------------------------------------------------------------
   Page
-------------------------------------------------------------------------- */

export default function Welcome({ auth }) {
    const canonicalUrl = typeof window !== 'undefined' ? window.location.href : 'https://shoppingli.example';

    const structuredData = {
        '@context': 'https://schema.org',
        '@type': 'SoftwareApplication',
        name: 'ShoppingLi',
        url: 'https://shoppingli.example',
        description: 'Inventory and product management for your shop — products, warehouses and stock in one clean workspace.',
        applicationCategory: 'BusinessApplication',
        operatingSystem: 'Any',
        offers: {
            '@type': 'AggregateOffer',
            lowPrice: '0',
            highPrice: '99',
            priceCurrency: 'USD',
        },
    };

    return (
        <>
            <Head title="Inventory & Product Management for Your Shop">
                <meta
                    name="description"
                    content="ShoppingLi brings your products, warehouses and stock into one clean workspace. Free plan, 14-day trial, secure per-business workspaces."
                />
                <meta property="og:type" content="website" />
                <meta property="og:site_name" content="ShoppingLi" />
                <meta property="og:title" content="ShoppingLi — Inventory & Product Management for Your Shop" />
                <meta
                    property="og:description"
                    content="Products, warehouses and stock in one clean workspace — no spreadsheets, no guesswork."
                />
                <meta property="og:url" content={canonicalUrl} />
                <meta name="twitter:card" content="summary_large_image" />
                <meta name="twitter:title" content="ShoppingLi — Inventory & Product Management for Your Shop" />
                <meta
                    name="twitter:description"
                    content="Products, warehouses and stock in one clean workspace — no spreadsheets, no guesswork."
                />
                <link rel="canonical" href={canonicalUrl} />
                <script
                    type="application/ld+json"
                    dangerouslySetInnerHTML={{ __html: JSON.stringify(structuredData) }}
                />
            </Head>

            <div className="bg-white font-sans text-ink antialiased selection:bg-brand-500 selection:text-white">
                <Nav auth={auth} />
                <main>
                    <Hero auth={auth} />
                    <CapabilityStrip />
                    <ProblemSolution />
                    <Features />
                    <HowItWorks />
                    <Pricing />
                    <Testimonial />
                    <Faq />
                    <FinalCta />
                </main>
                <Footer />
            </div>
        </>
    );
}