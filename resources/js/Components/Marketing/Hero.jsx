import { Link } from '@inertiajs/react';
import Reveal from './Reveal';
import { STOCK_ROWS } from './content';

export default function Hero() {
    return (
        <section id="top" className="relative overflow-hidden bg-ink text-white">
            {/* A ruled grid instead of the blurred radial blobs this section used
                to carry. Same job — give the flat dark field some structure —
                but it reads as drafting paper rather than glassmorphism. */}
            <div aria-hidden="true" className="sl-ledger-grid pointer-events-none absolute inset-0" />

            <div className="relative mx-auto grid max-w-7xl items-center gap-14 px-4 pb-24 pt-20 sm:px-6 lg:grid-cols-2 lg:px-8 lg:pb-32 lg:pt-28">
                <div>
                    <Reveal>
                        <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-400">
                            Inventory &amp; product management for your shop
                        </p>
                    </Reveal>

                    <Reveal delay={80}>
                        <h1 className="mt-5 font-display text-[clamp(2.5rem,6.5vw,4.5rem)] leading-[1.05] tracking-tight text-balance">
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
                                className="sl-cta sl-press rounded-md bg-brand-500 px-6 py-3 text-center text-sm font-semibold text-white transition-[color,background-color,border-color,transform] duration-300 ease-out hover:bg-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-ink"
                            >
                                Start free — no card required
                            </Link>
                            <a
                                href="#pricing"
                                className="sl-press rounded-md border border-white/20 px-6 py-3 text-center text-sm font-semibold text-white transition-[color,background-color,border-color,transform] duration-150 ease-out hover:border-white/50 hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                            >
                                See pricing
                            </a>
                        </div>
                    </Reveal>

                    <Reveal delay={320}>
                        <ul className="mt-8 flex flex-wrap gap-x-5 gap-y-2 text-sm text-white/50">
                            {/* Short rules instead of round dots — same reassurance
                                list, drawn with the page's hairline language. */}
                            {['14-day free trial', 'Up in minutes', 'One secure workspace per business'].map((item) => (
                                <li key={item} className="flex items-center gap-2">
                                    <span aria-hidden="true" className="h-px w-3 bg-brand-500" />
                                    {item}
                                </li>
                            ))}
                        </ul>
                    </Reveal>
                </div>

                <Reveal delay={160} className="relative">
                    <div className="rounded-sm border border-white/10 bg-white/[0.02] p-5 sm:p-6">
                        <div className="flex items-center justify-between border-b border-white/10 pb-4">
                            <p className="text-sm font-semibold text-white">North warehouse</p>
                            <span className="inline-flex items-center gap-1.5 text-xs font-medium text-brand-300">
                                <span className="sl-pulse h-1.5 w-1.5 rounded-full bg-brand-400" aria-hidden="true" />
                                Live
                            </span>
                        </div>

                        {/* Rows separated by hairlines rather than nested cards: the
                            panel is a ledger excerpt, so the rules do the work that
                            three rounded boxes were doing. Tabular figures keep the
                            quantities aligned the way a real stock report does. */}
                        <ul className="divide-y divide-white/10">
                            {STOCK_ROWS.map((row, index) => (
                                <li
                                    key={row.name}
                                    className="sl-row flex items-center justify-between gap-4 py-3"
                                    style={{ animationDelay: `${320 + index * 80}ms` }}
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium text-white">{row.name}</p>
                                        <p className="text-xs text-white/50">{row.category}</p>
                                    </div>
                                    <div className="flex shrink-0 items-baseline gap-3">
                                        <span className="tabular-nums text-base font-bold text-white">{row.qty}</span>
                                        <span
                                            className={`tabular-nums text-xs font-semibold ${
                                                row.positive ? 'text-white/50' : 'text-brand-300'
                                            }`}
                                        >
                                            {row.delta}
                                        </span>
                                    </div>
                                </li>
                            ))}
                        </ul>

                        <div className="mt-4 border-t border-white/10 pt-4">
                            <div className="flex items-baseline justify-between text-xs">
                                <span className="text-white/50">Total SKUs</span>
                                <span className="tabular-nums font-semibold text-white">1,284</span>
                            </div>
                            <div className="mt-2 h-1 overflow-hidden bg-white/10" aria-hidden="true">
                                <div className="sl-fill-x h-1 w-2/3 bg-brand-500" />
                            </div>
                        </div>
                    </div>
                </Reveal>
            </div>
        </section>
    );
}