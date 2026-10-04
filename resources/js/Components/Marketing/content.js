import {
    BoxIcon,
    TagIcon,
    MapPinIcon,
    ArrowsIcon,
    ChartIcon,
    ShieldIcon,
    TableIcon,
    PulseIcon,
} from './MarketingIcons';

export const NAV_LINKS = [
    { href: '#features', label: 'Features' },
    { href: '#how-it-works', label: 'How it works' },
    { href: '#pricing', label: 'Pricing' },
    { href: '#faq', label: 'FAQ' },
];

export const CAPABILITIES = ['Products', 'Categories', 'Warehouses', 'Stock movements', 'Orders', 'Payments'];

export const PAINS = [
    {
        icon: TableIcon,
        title: 'Replace the spreadsheet',
        body: 'Your catalog, warehouses and movements in one source of truth. No more duplicated sheets, no more “we’ll fix it later” rows.',
    },
    {
        icon: PulseIcon,
        title: 'Track stock in real time',
        body: 'Every product, every location, always current. See what you actually have before you promise it to someone.',
    },
    {
        icon: MapPinIcon,
        title: 'Run multiple warehouses',
        body: 'Spread inventory across locations and keep a single, honest view of everything — from one dashboard.',
    },
];

export const FEATURES = [
    {
        icon: BoxIcon,
        title: 'One catalog, always up to date',
        body: 'Products with categories and prices in a single place. Update once, and every shelf sees it.',
    },
    {
        icon: TagIcon,
        title: 'Your catalog, your way',
        body: 'Structure categories the way your shop actually thinks, so the right product is always easy to find.',
    },
    {
        icon: MapPinIcon,
        title: 'Know what’s where',
        body: 'Run multiple warehouses from one dashboard — quantities and locations at a glance.',
    },
    {
        icon: ArrowsIcon,
        title: 'Every movement, recorded',
        body: 'Stock in, stock out — every movement leaves a trace you can follow. Available on Growth plans and up.',
    },
    {
        icon: ChartIcon,
        title: 'Numbers you can act on',
        body: 'A dashboard that shows what you hold, what moved, and what’s selling — in real time.',
    },
    {
        icon: ShieldIcon,
        title: 'Private by design',
        body: 'Each business runs in its own secured workspace. Your data stays yours, full stop.',
    },
];

export const STEPS = [
    {
        n: '01',
        title: 'Create your workspace',
        body: 'Pick a plan, choose your address, and you’re live in minutes. The trial needs no card.',
    },
    {
        n: '02',
        title: 'Add your catalog',
        body: 'Bring in products, add categories, set up your warehouses. Start as simple as you like.',
    },
    {
        n: '03',
        title: 'Track and grow',
        body: 'Record movements, watch your dashboard, and upgrade when the shop is ready.',
    },
];

export const PLANS = [
    {
        name: 'Free',
        price: '$0',
        period: 'free forever',
        note: 'One shop, fully organized.',
        features: ['Products & categories', '1 warehouse', 'Dashboard overview'],
        highlight: false,
        cta: 'Start free',
    },
    {
        name: 'Growth',
        price: '$15',
        period: '/mo',
        note: 'For shops that track movements.',
        features: ['Everything in Free', 'Inventory movements', 'More users & warehouses'],
        highlight: true,
        cta: 'Start 14-day trial',
    },
    {
        name: 'Pro',
        price: '$29',
        period: '/mo',
        note: 'For growing, multi-location shops.',
        features: ['Everything in Growth', 'Higher storage & limits', 'Multi-warehouse visibility'],
        highlight: false,
        cta: 'Start 14-day trial',
    },
    {
        name: 'Enterprise',
        price: '$99',
        period: '/mo',
        note: 'For teams at full scale.',
        features: ['Everything in Pro', 'Top limits & storage', 'Priority support'],
        highlight: false,
        cta: 'Start 14-day trial',
    },
];

export const FAQS = [
    {
        q: 'Is it really free?',
        a: 'Yes — the Free plan costs nothing, forever. Paid plans begin with a 14-day free trial, and nothing is charged during the trial.',
    },
    {
        q: 'Can I try without a card?',
        a: 'Absolutely. Trials and the Free plan have no charge; payment is only entered at checkout if you pick a paid plan.',
    },
    {
        q: 'Can I run several warehouses?',
        a: 'Yes. Warehouse support scales with your plan, from a single location on Free up to full multi-warehouse tracking.',
    },
    {
        q: 'What happens if I hit a limit?',
        a: 'Your workspace stays fully online. To grow beyond a plan’s users, products, warehouses or storage, simply upgrade.',
    },
    {
        q: 'How is my data kept private?',
        a: 'Every business runs in its own isolated workspace with its own database and access rules — built multi-tenant from the ground up.',
    },
];

export const STOCK_ROWS = [
    { name: 'Wireless earbuds', category: 'Audio', qty: '342', delta: '+18 in', positive: true },
    { name: 'Canvas tote', category: 'Bags', qty: '96', delta: '−12 out', positive: false },
    { name: 'Linen throw', category: 'Home', qty: '204', delta: '+7 in', positive: true },
];
