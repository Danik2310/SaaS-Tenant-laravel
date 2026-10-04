import Reveal from './Reveal';

export default function Testimonial() {
    return (
        <section className="bg-ink py-24 text-white">
            <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <Reveal>
                    <blockquote className="text-center">
                        <p aria-hidden="true" className="font-display text-6xl leading-none text-brand-500">
                            “
                        </p>
                        <p className="-mt-6 text-2xl font-medium leading-relaxed sm:text-3xl sm:leading-snug">
                            We stopped reconciling three spreadsheets every Friday. ShoppingLi just shows us the truth —
                            what we have, where it is, and what moved.
                        </p>
                        <footer className="mt-8 text-sm font-semibold uppercase tracking-wider text-white/50">
                            Store manager — small retail
                        </footer>
                    </blockquote>
                </Reveal>
            </div>
        </section>
    );
}
