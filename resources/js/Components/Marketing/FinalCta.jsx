import { Link } from '@inertiajs/react';
import Reveal from './Reveal';

export default function FinalCta() {
    return (
        <section className="relative overflow-hidden bg-ink py-24 text-white lg:py-32">
            <div aria-hidden="true" className="sl-ledger-grid pointer-events-none absolute inset-0" />
            <div className="relative mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
                <Reveal>
                    {/* The only display heading outside the hero. Keeping the two
                        ends of the page in Archivo Black and everything between
                        them in the body face is what gives the page a hierarchy
                        instead of a uniform shout. */}
                    <h2 className="font-display text-[clamp(2rem,5vw,3.5rem)] leading-tight tracking-tight text-balance">
                        Ready to get organized?
                    </h2>
                    <p className="mt-5 text-pretty text-lg text-white/70">
                        Create your workspace — it takes minutes. No card until you choose a paid plan.
                    </p>
                    <div className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <Link
                            href={route('register.tenant')}
                            className="sl-cta sl-press w-full rounded-md bg-brand-500 px-8 py-3.5 text-sm font-semibold text-white transition-[color,background-color,border-color,transform] duration-300 ease-out hover:bg-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-ink sm:w-auto"
                        >
                            Start free
                        </Link>
                        <Link
                            href={route('login')}
                            className="sl-press w-full rounded-md border border-white/20 px-8 py-3.5 text-sm font-semibold text-white transition-[color,background-color,border-color,transform] duration-150 ease-out hover:border-white/50 hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 sm:w-auto"
                        >
                            Log in
                        </Link>
                    </div>
                </Reveal>
            </div>
        </section>
    );
}
