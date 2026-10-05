import Reveal from './Reveal';
import { PAINS } from './content';

export default function ProblemSolution() {
    return (
        <section className="bg-white py-24 text-ink">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <Reveal>
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-700">Why ShoppingLi</p>
                    <h2 className="mt-4 max-w-2xl text-[clamp(1.75rem,4vw,2.25rem)] font-bold leading-tight tracking-tight text-balance">
                        Spreadsheets were never built for inventory.
                    </h2>
                    <p className="mt-5 max-w-2xl text-pretty text-lg leading-relaxed text-ink/60">
                        When products live in a spreadsheet and stock lives somewhere else, nothing quite adds up.
                        ShoppingLi brings it all into one place — so you can trust the numbers at a glance.
                    </p>
                </Reveal>

                {/* Same ledger head as Features: index, rule, mark. Repeating the
                    device across two consecutive sections is what makes the page
                    feel drawn rather than assembled. */}
                <div className="mt-14 grid gap-8 md:grid-cols-3">
                    {PAINS.map((item, index) => (
                        <Reveal key={item.title} delay={index * 80} className="h-full">
                            <article className="sl-spine flex h-full flex-col rounded-sm border border-ink/10 bg-white p-7 transition-colors duration-150 ease-out hover:border-ink/25">
                                <div className="flex items-center gap-3">
                                    <span className="sl-index text-sm text-brand-700">
                                        {String(index + 1).padStart(2, '0')}
                                    </span>
                                    <span aria-hidden="true" className="h-px flex-1 bg-ink/10" />
                                    <item.icon className="h-4 w-4 shrink-0 text-ink/50" />
                                </div>
                                <h3 className="mt-6 text-lg font-bold">{item.title}</h3>
                                <p className="mt-2 text-pretty leading-relaxed text-ink/60">{item.body}</p>
                            </article>
                        </Reveal>
                    ))}
                </div>
            </div>
        </section>
    );
}