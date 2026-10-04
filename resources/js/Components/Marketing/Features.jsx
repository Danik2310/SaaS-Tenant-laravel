import Reveal from './Reveal';
import { FEATURES } from './content';

export default function Features() {
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
                        <Reveal key={item.title} delay={(index % 3) * 80} className="h-full">
                            <article className="sl-lift group h-full rounded-2xl border border-gray-200 bg-white p-7 transition-[transform,box-shadow,border-color] duration-300 ease-out hover:border-brand-300 hover:shadow-lg hover:shadow-brand-500/10">
                                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 transition-[color,background-color] duration-150 ease-out group-hover:bg-brand-500 group-hover:text-white">
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
