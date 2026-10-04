import Reveal from './Reveal';
import { STEPS } from './content';

export default function HowItWorks() {
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
                        <Reveal key={step.n} as="li" delay={index * 80}>
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
