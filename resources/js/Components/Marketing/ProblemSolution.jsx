import Reveal from './Reveal';
import { PAINS } from './content';

export default function ProblemSolution() {
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
                        <Reveal key={item.title} delay={index * 80} className="h-full">
                            <div className="sl-lift h-full rounded-2xl border border-gray-200 bg-white p-7 transition-[transform,box-shadow,border-color] duration-300 ease-out hover:border-brand-300 hover:shadow-lg hover:shadow-brand-500/10">
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
