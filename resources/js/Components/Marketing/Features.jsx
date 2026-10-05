import Reveal from './Reveal';
import { FEATURES } from './content';

// Bento rhythm on the 3-column grid: two wide, one narrow, three times over —
// 2-1 / 1-2 / 2-1. Wide cards are the two capabilities the plan gates on
// (catalog and movements), so the layout argues the product rather than
// decorating it. Every feature stays present; only the footprint changes.
const WIDE = new Set([0, 3, 4]);

export default function Features() {
    return (
        <section id="features" className="scroll-mt-24 border-t border-ink/10 bg-gray-50 py-20 text-ink lg:py-28">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <Reveal>
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-700">Everything in one place</p>
                    <div className="mt-4 flex flex-col justify-between gap-6 md:flex-row md:items-end">
                        <h2 className="max-w-xl text-[clamp(1.75rem,4vw,2.25rem)] font-bold leading-tight tracking-tight text-balance">
                            Built for the daily grind of running a shop.
                        </h2>
                        <p className="max-w-sm text-pretty text-base leading-relaxed text-ink/60">
                            Everything you already juggle — products, categories, warehouses, movements — in a
                            workspace that behaves like one.
                        </p>
                    </div>
                </Reveal>

                <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {FEATURES.map((item, index) => {
                        const wide = WIDE.has(index);

                        return (
                            <Reveal key={item.title} delay={Math.min(index, 2) * 80} className={`h-full ${wide ? 'lg:col-span-2' : ''}`}>
                                <article className="sl-spine flex h-full flex-col rounded-sm border border-ink/10 bg-white p-7 transition-colors duration-150 ease-out hover:border-ink/25">
                                    {/* Index, rule, mark. Replaces the tinted icon tile
                                        that gave the section away as a template — the
                                        numeral carries the position, the hairline draws
                                        the row, and the domain icon stays as a quiet
                                        signal rather than a pastel square. */}
                                    <div className="flex items-center gap-3">
                                        <span className="sl-index text-sm text-brand-700">
                                            {String(index + 1).padStart(2, '0')}
                                        </span>
                                        <span aria-hidden="true" className="h-px flex-1 bg-ink/10" />
                                        <item.icon className="h-4 w-4 shrink-0 text-ink/50" />
                                    </div>
                                    <h3 className={`mt-6 font-bold leading-snug ${wide ? 'text-xl sm:text-2xl' : 'text-lg'}`}>
                                        {item.title}
                                    </h3>
                                    <p className="mt-2 text-pretty leading-relaxed text-ink/60">{item.body}</p>
                                </article>
                            </Reveal>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}