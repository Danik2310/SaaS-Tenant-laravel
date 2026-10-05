import Reveal from './Reveal';

export default function Testimonial() {
    return (
        <section className="border-y border-white/5 bg-ink py-20 text-white lg:py-24">
            <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <Reveal from="scale">
                    <blockquote className="text-center">
                        <p aria-hidden="true" className="font-display text-6xl leading-none text-brand-500">
                            “
                        </p>
                        <p className="-mt-6 text-pretty text-[clamp(1.5rem,3.5vw,2rem)] font-medium leading-relaxed sm:leading-snug">
                            We stopped reconciling three spreadsheets every Friday. ShoppingLi just shows us the truth —
                            what we have, where it is, and what moved.
                        </p>
                        <footer className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                            <span
                                aria-hidden="true"
                                className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-white/10 bg-white/[0.06] font-display text-sm text-brand-400"
                            >
                                SM
                            </span>
                            <span className="text-sm font-semibold uppercase tracking-wider text-white/50">
                                Store manager — small retail
                            </span>
                        </footer>
                    </blockquote>
                </Reveal>
            </div>
        </section>
    );
}