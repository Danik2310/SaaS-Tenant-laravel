import Wordmark from '@/Components/BrandLogo';
import { NAV_LINKS } from './content';

export default function Footer() {
    return (
        <footer className="border-t border-white/10 bg-ink py-14 text-white">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex flex-col items-start justify-between gap-10 md:flex-row md:items-center">
                    <div>
                        <Wordmark />
                        <p className="mt-3 text-sm text-white/50">Run your whole shop from one line.</p>
                    </div>

                    <nav aria-label="Footer" className="flex flex-wrap gap-x-8 gap-y-3">
                        {NAV_LINKS.map((link) => (
                            <a
                                key={link.href}
                                href={link.href}
                                className="text-sm text-white/70 transition-colors duration-150 ease-out hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded-md"
                            >
                                {link.label}
                            </a>
                        ))}
                    </nav>
                </div>

                <div className="mt-12 flex flex-col items-start justify-between gap-4 border-t border-white/10 pt-8 text-sm text-white/40 md:flex-row md:items-center">
                    <p>© {new Date().getFullYear()} ShoppingLi. All rights reserved.</p>
                    <div className="flex gap-6">
                        <span>Privacy</span>
                        <span>Terms</span>
                    </div>
                </div>
            </div>
        </footer>
    );
}
