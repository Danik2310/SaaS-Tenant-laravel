import { Link } from '@inertiajs/react';
import Reveal from './Reveal';
import { PLANS } from './content';

export default function Pricing() {
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
                            className="sl-press shrink-0 rounded-full border border-ink px-6 py-3 text-center text-sm font-semibold text-ink transition-[color,background-color,border-color,transform] duration-150 ease-out hover:bg-ink hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                        >
                            Compare details at signup
                        </Link>
                    </div>
                </Reveal>

                <div className="mt-14 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    {PLANS.map((plan, index) => (
                        <Reveal key={plan.name} delay={index * 80} className="h-full">
                            <article
                                className={`sl-lift relative flex h-full flex-col rounded-2xl border p-7 transition-[transform,box-shadow,border-color] duration-300 ease-out ${
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
                                        className={`sl-press w-full rounded-full px-5 py-2.5 text-center text-sm font-semibold transition-[color,background-color,border-color,transform] duration-150 ease-out focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 ${
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
