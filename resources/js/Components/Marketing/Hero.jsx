import { Link } from '@inertiajs/react';
import Reveal from './Reveal';
import { STOCK_ROWS } from './content';

export default function Hero() {
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
                                className="sl-cta sl-press rounded-full bg-brand-500 px-6 py-3 text-center text-sm font-semibold text-white transition-[color,background-color,border-color,transform] duration-300 ease-out hover:bg-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-ink"
                            >
                                Start free — no card required
                            </Link>
                            <a
                                href="#pricing"
                                className="sl-press rounded-full border border-white/20 px-6 py-3 text-center text-sm font-semibold text-white transition-[color,background-color,border-color,transform] duration-150 ease-out hover:border-white/50 hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
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

                <Reveal delay={160} className="relative">
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
