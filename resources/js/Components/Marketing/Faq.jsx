import { useState } from 'react';
import Reveal from './Reveal';
import { FAQS } from './content';

export default function Faq() {
    const [openIndex, setOpenIndex] = useState(0);

    return (
        <section id="faq" className="scroll-mt-24 bg-white py-24 text-ink">
            <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <Reveal>
                    <p className="text-center text-xs font-bold uppercase tracking-[0.2em] text-brand-600">FAQ</p>
                    <h2 className="mt-4 text-center font-display text-3xl leading-tight tracking-tight sm:text-4xl">
                        Questions, answered.
                    </h2>
                </Reveal>

                <div className="mt-12 divide-y divide-gray-200">
                    {FAQS.map((faq, index) => {
                        const open = openIndex === index;

                        return (
                            <Reveal key={faq.q} delay={index * 80}>
                                <div>
                                    <button
                                        type="button"
                                        onClick={() => setOpenIndex(open ? -1 : index)}
                                        aria-expanded={open}
                                        aria-controls={`faq-panel-${index}`}
                                        className="sl-press flex w-full items-center justify-between gap-6 py-5 text-left transition-transform duration-150 ease-out focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded-md"
                                    >
                                        <span className="text-base font-bold sm:text-lg">{faq.q}</span>
                                        <span
                                            aria-hidden="true"
                                            className={`h-6 w-6 shrink-0 text-brand-500 transition-transform duration-150 ease-out ${
                                                open ? 'rotate-45' : ''
                                            }`}
                                        >
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" className="h-6 w-6">
                                                <path d="M12 5v14M5 12h14" />
                                            </svg>
                                        </span>
                                    </button>
                                    <div
                                        id={`faq-panel-${index}`}
                                        role="region"
                                        hidden={!open}
                                        className={`pb-5 pr-10 leading-relaxed text-gray-600 ${open ? 'sl-panel-in' : ''}`}
                                    >
                                        {faq.a}
                                    </div>
                                </div>
                            </Reveal>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
