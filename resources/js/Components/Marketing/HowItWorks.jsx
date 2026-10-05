import Reveal from './Reveal';
import { STEPS } from './content';

export default function HowItWorks() {
    return (
        <section id="how-it-works" className="scroll-mt-24 border-t border-ink/10 bg-white py-20 text-ink lg:py-28">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <Reveal>
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-700">How it works</p>
                    <h2 className="mt-4 max-w-2xl text-[clamp(1.75rem,4vw,2.25rem)] font-bold leading-tight tracking-tight text-balance">
                        From spreadsheet chaos to order — in three steps.
                    </h2>
                </Reveal>

                {/* The rail ties 01 → 02 → 03 together on desktop, where the
                    per-step divider below is hidden. It sits at the numerals'
                    optical centre: text-5xl uses a 1 line-height, so half of
                    3rem lands on top-6. The <ol> is positioned so it paints
                    above the rail, and each numeral carries the section
                    background so the line reads as passing behind the digits
                    rather than through them. */}
                <div className="relative mt-14">
                    <Reveal
                        from="left"
                        className="pointer-events-none absolute inset-x-0 top-6 hidden h-px md:block"
                    >
                        <div className="h-px w-full bg-gradient-to-r from-brand-500/50 via-brand-500/25 to-brand-500/5" />
                    </Reveal>

                    <ol className="relative grid gap-10 md:grid-cols-3">
                        {STEPS.map((step, index) => (
                            <Reveal key={step.n} as="li" delay={index * 80}>
                                <div className="relative">
                                    <span className="relative z-10 bg-white pr-3 font-display text-5xl text-brand-600">
                                        {step.n}
                                    </span>
                                    <h3 className="mt-4 text-lg font-bold">{step.title}</h3>
                                    <p className="mt-2 text-pretty leading-relaxed text-ink/60">{step.body}</p>
                                    <div
                                        aria-hidden="true"
                                        className="mt-6 h-px w-full bg-gradient-to-r from-brand-500/60 to-transparent md:hidden"
                                    />
                                </div>
                            </Reveal>
                        ))}
                    </ol>
                </div>
            </div>
        </section>
    );
}