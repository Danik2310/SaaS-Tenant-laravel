import { Link } from '@inertiajs/react';
import Reveal from './Reveal';

export default function FinalCta() {
    return (
        <section className="relative overflow-hidden bg-ink py-24 text-white">
            <div aria-hidden="true" className="pointer-events-none absolute -left-24 top-1/2 h-72 w-72 -translate-y-1/2 rounded-full bg-brand-500/20 blur-3xl" />
            <div className="relative mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
                <Reveal>
                    <h2 className="font-display text-3xl leading-tight tracking-tight sm:text-5xl">
                        Ready to get organized?
                    </h2>
                    <p className="mt-5 text-lg text-white/70">
                        Create your workspace — it takes minutes. No card until you choose a paid plan.
                    </p>
                    <div className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <Link
                            href={route('register.tenant')}
                            className="sl-cta sl-press w-full rounded-full bg-brand-500 px-8 py-3.5 text-sm font-semibold text-white transition-[color,background-color,border-color,transform] duration-300 ease-out hover:bg-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-ink sm:w-auto"
                        >
                            Start free
                        </Link>
                        <Link
                            href={route('login')}
                            className="sl-press w-full rounded-full border border-white/20 px-8 py-3.5 text-sm font-semibold text-white transition-[color,background-color,border-color,transform] duration-150 ease-out hover:border-white/50 hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 sm:w-auto"
                        >
                            Log in
                        </Link>
                    </div>
                </Reveal>
            </div>
        </section>
    );
}
