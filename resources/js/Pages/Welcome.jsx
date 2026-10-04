import { Head } from '@inertiajs/react';
import Nav from '@/Components/Marketing/Nav';
import Hero from '@/Components/Marketing/Hero';
import CapabilityStrip from '@/Components/Marketing/CapabilityStrip';
import ProblemSolution from '@/Components/Marketing/ProblemSolution';
import Features from '@/Components/Marketing/Features';
import HowItWorks from '@/Components/Marketing/HowItWorks';
import Pricing from '@/Components/Marketing/Pricing';
import Testimonial from '@/Components/Marketing/Testimonial';
import Faq from '@/Components/Marketing/Faq';
import FinalCta from '@/Components/Marketing/FinalCta';
import Footer from '@/Components/Marketing/Footer';

export default function Welcome({ auth }) {
    const canonicalUrl = typeof window !== 'undefined' ? window.location.href : 'https://shoppingli.example';

    const structuredData = {
        '@context': 'https://schema.org',
        '@type': 'SoftwareApplication',
        name: 'ShoppingLi',
        url: 'https://shoppingli.example',
        description: 'Inventory and product management for your shop — products, warehouses and stock in one clean workspace.',
        applicationCategory: 'BusinessApplication',
        operatingSystem: 'Any',
        offers: {
            '@type': 'AggregateOffer',
            lowPrice: '0',
            highPrice: '99',
            priceCurrency: 'USD',
        },
    };

    return (
        <>
            <Head title="Inventory & Product Management for Your Shop">
                <meta
                    name="description"
                    content="ShoppingLi brings your products, warehouses and stock into one clean workspace. Free plan, 14-day trial, secure per-business workspaces."
                />
                <meta property="og:type" content="website" />
                <meta property="og:site_name" content="ShoppingLi" />
                <meta property="og:title" content="ShoppingLi — Inventory & Product Management for Your Shop" />
                <meta
                    property="og:description"
                    content="Products, warehouses and stock in one clean workspace — no spreadsheets, no guesswork."
                />
                <meta property="og:url" content={canonicalUrl} />
                <meta name="twitter:card" content="summary_large_image" />
                <meta name="twitter:title" content="ShoppingLi — Inventory & Product Management for Your Shop" />
                <meta
                    name="twitter:description"
                    content="Products, warehouses and stock in one clean workspace — no spreadsheets, no guesswork."
                />
                <link rel="canonical" href={canonicalUrl} />
                <script
                    type="application/ld+json"
                    dangerouslySetInnerHTML={{ __html: JSON.stringify(structuredData) }}
                />
            </Head>

            <div className="sl-marketing bg-white font-sans text-ink antialiased selection:bg-brand-500 selection:text-white">
                <Nav auth={auth} />
                <main>
                    <Hero />
                    <CapabilityStrip />
                    <ProblemSolution />
                    <Features />
                    <HowItWorks />
                    <Pricing />
                    <Testimonial />
                    <Faq />
                    <FinalCta />
                </main>
                <Footer />
            </div>
        </>
    );
}
