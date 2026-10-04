import Reveal from './Reveal';
import { CAPABILITIES } from './content';

export default function CapabilityStrip() {
    return (
        <section aria-label="What ShoppingLi manages" className="border-t border-white/5 bg-ink pb-14 text-white">
            <Reveal>
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <p className="text-center text-xs font-bold uppercase tracking-[0.2em] text-white/40">
                        Made for the way shops really run
                    </p>
                    <ul className="mt-6 flex flex-wrap items-center justify-center gap-3">
                        {CAPABILITIES.map((item) => (
                            <li
                                key={item}
                                className="rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-sm font-medium text-white/80 transition-[color,background-color,border-color] duration-150 ease-out hover:border-brand-500/50 hover:text-white"
                            >
                                {item}
                            </li>
                        ))}
                    </ul>
                </div>
            </Reveal>
        </section>
    );
}
