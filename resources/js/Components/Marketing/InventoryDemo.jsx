import { useMemo, useState } from 'react';
import Reveal from './Reveal';
import { DEMO_CATEGORIES, DEMO_ROWS, DEMO_WAREHOUSES, MOVEMENT_LABEL } from './inventoryDemoData';

const COLUMNS = [
    { key: 'name', label: 'Product', align: 'left' },
    { key: 'category', label: 'Category', align: 'left' },
    { key: 'warehouse', label: 'Warehouse', align: 'left' },
    { key: 'onHand', label: 'On hand', align: 'right' },
];

export default function InventoryDemo() {
    const [query, setQuery] = useState('');
    const [category, setCategory] = useState('all');
    const [warehouse, setWarehouse] = useState('all');
    const [lowOnly, setLowOnly] = useState(false);
    const [sort, setSort] = useState({ key: 'name', dir: 'asc' });

    const rows = useMemo(() => {
        const needle = query.trim().toLowerCase();

        const filtered = DEMO_ROWS.filter((row) => {
            if (lowOnly && row.onHand >= row.reorderPoint) {
                return false;
            }
            if (category !== 'all' && row.category !== category) {
                return false;
            }
            if (warehouse !== 'all' && row.warehouse !== warehouse) {
                return false;
            }
            if (needle && !`${row.name} ${row.sku}`.toLowerCase().includes(needle)) {
                return false;
            }

            return true;
        });

        const direction = sort.dir === 'asc' ? 1 : -1;

        return [...filtered].sort((a, b) => {
            if (sort.key === 'onHand') {
                return (a.onHand - b.onHand) * direction;
            }

            return a[sort.key].localeCompare(b[sort.key]) * direction;
        });
    }, [query, category, warehouse, lowOnly, sort]);

    const toggleSort = (key) => {
        setSort((previous) =>
            previous.key === key
                ? { key, dir: previous.dir === 'asc' ? 'desc' : 'asc' }
                : { key, dir: 'asc' }
        );
    };

    // The cross-fade is replayed by remounting the tbody against this key, so
    // rows are never reordered by an animated layout — the transform/opacity
    // contract only allows the 150ms opacity dip. Search is deliberately absent
    // from the key: replaying it on every keystroke would strobe while typing,
    // and an instant response is what a text field should feel like anyway.
    const fadeKey = `${category}|${warehouse}|${lowOnly}|${sort.key}|${sort.dir}`;

    const lowStockCount = DEMO_ROWS.filter((row) => row.onHand < row.reorderPoint).length;

    return (
        <section id="inventory" className="scroll-mt-24 border-t border-ink/10 bg-ink py-20 text-white lg:py-28">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <Reveal>
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-400">
                        One view, every warehouse
                    </p>
                    <div className="mt-4 flex flex-col justify-between gap-6 md:flex-row md:items-end">
                        <h2 className="max-w-2xl text-[clamp(1.75rem,4vw,2.25rem)] font-bold leading-tight tracking-tight text-balance">
                            Your stock, down to the last unit.
                        </h2>
                        <p className="max-w-sm text-pretty text-base leading-relaxed text-white/60">
                            Every sort, filter and low-stock flag is live. Go ahead and click.
                        </p>
                    </div>
                </Reveal>

                <Reveal delay={80}>
                    <div className="mt-12 rounded-sm border border-white/10 bg-white/[0.02]">
                        <div className="flex flex-col gap-4 border-b border-white/10 p-5 lg:flex-row lg:items-center lg:justify-between">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <label className="sr-only" htmlFor="inventory-search">
                                    Search by product name or SKU
                                </label>
                                <input
                                    id="inventory-search"
                                    type="search"
                                    value={query}
                                    onChange={(event) => setQuery(event.target.value)}
                                    placeholder="Search name or SKU…"
                                    // The search field and the warehouse select carry no text of their own, so their
// outline is the only thing identifying them as controls: WCAG 2.2 SC 1.4.11
// wants 3:1 against the background, which needs border-white/35 on ink. The
// buttons and chips below keep the lighter hairline because they are already
// identified by their own 9.7:1 label.
className="rounded-sm border border-white/35 bg-transparent px-3 py-2 text-sm text-white placeholder:text-white/50 focus:border-brand-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                                />

                                <label className="sr-only" htmlFor="inventory-warehouse">
                                    Filter by warehouse
                                </label>
                                <select
                                    id="inventory-warehouse"
                                    value={warehouse}
                                    onChange={(event) => setWarehouse(event.target.value)}
                                    className="rounded-sm border border-white/35 bg-ink px-3 py-2 text-sm text-white focus:border-brand-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                                >
                                    <option value="all">All warehouses</option>
                                    {DEMO_WAREHOUSES.map((name) => (
                                        <option key={name} value={name}>
                                            {name}
                                        </option>
                                    ))}
                                </select>

                                <button
                                    type="button"
                                    aria-pressed={lowOnly}
                                    onClick={() => setLowOnly((value) => !value)}
                                    className={`sl-press rounded-sm border px-3 py-2 text-sm font-medium transition-colors duration-150 ease-out focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 ${
                                        lowOnly
                                            ? 'border-brand-500 bg-brand-500 text-white'
                                            : 'border-white/15 text-white/70 hover:border-white/40 hover:text-white'
                                    }`}
                                >
                                    Low stock only
                                </button>
                            </div>

                            {/* Announced on every filter change, so a screen reader
                                user hears the result without hunting for it. */}
                            <div className="flex items-center gap-3">
                                <p aria-live="polite" className="tabular-nums text-sm text-white/50">
                                    {rows.length} of {DEMO_ROWS.length} products
                                </p>
                                <span className="text-sm text-white/50">Sample data</span>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2 border-b border-white/10 px-5 py-4">
                            <span className="mr-1 text-xs font-bold uppercase tracking-[0.2em] text-white/50">
                                Category
                            </span>
                            {['all', ...DEMO_CATEGORIES].map((name) => {
                                const active = category === name;

                                return (
                                    <button
                                        key={name}
                                        type="button"
                                        aria-pressed={active}
                                        onClick={() => setCategory(name)}
                                        className={`rounded-sm border px-3 py-1.5 text-xs font-medium transition-colors duration-150 ease-out focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 ${
                                            active
                                                ? 'border-brand-500 bg-brand-500 text-white'
                                                : 'border-white/15 text-white/60 hover:border-white/40 hover:text-white'
                                        }`}
                                    >
                                        {name === 'all' ? 'All' : name}
                                    </button>
                                );
                            })}
                        </div>

                        {/* tabIndex + region label make the horizontal scroll
                            reachable by keyboard on narrow screens, which is the
                            standard accessible pattern for a wide data table. */}
                        <div
                            role="region"
                            aria-label="Stock levels by product and warehouse"
                            tabIndex={0}
                            className="overflow-x-auto focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500"
                        >
                            <table className="w-full min-w-[720px] text-left">
                                <caption className="sr-only">
                                    Example stock levels across products and warehouses, sorted by{' '}
                                    {sort.key}. {lowStockCount} of {DEMO_ROWS.length} products are below their
                                    reorder point.
                                </caption>
                                <thead>
                                    <tr className="border-b border-white/10">
                                        {COLUMNS.map((column) => {
                                            const active = sort.key === column.key;

                                            return (
                                                <th
                                                    key={column.key}
                                                    scope="col"
                                                    // ARIA APG applies aria-sort to the
                                                    // sorted column only; leaving the
                                                    // rest without the attribute is
                                                    // what "not sorted" means.
                                                    aria-sort={
                                                        active ? (sort.dir === 'asc' ? 'ascending' : 'descending') : undefined
                                                    }
                                                    className={`px-5 py-3 text-xs font-bold uppercase tracking-[0.2em] ${
                                                        column.align === 'right' ? 'text-right' : 'text-left'
                                                    } text-white/50`}
                                                >
                                                    <button
                                                        type="button"
                                                        onClick={() => toggleSort(column.key)}
                                                        className={`inline-flex items-center gap-1.5 py-1 transition-colors duration-150 ease-out hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 ${
                                                            active ? 'text-brand-400' : ''
                                                        }`}
                                                    >
                                                        {column.label}
                                                        <span aria-hidden="true" className="text-[0.7em] leading-none">
                                                            {active ? (sort.dir === 'asc' ? '▲' : '▼') : '↕'}
                                                        </span>
                                                    </button>
                                                </th>
                                            );
                                        })}
                                        <th
                                            scope="col"
                                            className="px-5 py-3 text-xs font-bold uppercase tracking-[0.2em] text-white/50"
                                        >
                                            Last movement
                                        </th>
                                    </tr>
                                </thead>

                                <tbody key={fadeKey} className="sl-demo-swap divide-y divide-white/5">
                                    {rows.length === 0 && (
                                        <tr>
                                            <td colSpan={COLUMNS.length + 1} className="px-5 py-14 text-center text-sm text-white/50">
                                                No products match these filters.
                                            </td>
                                        </tr>
                                    )}

                                    {rows.map((row) => {
                                        const low = row.onHand < row.reorderPoint;

                                        return (
                                            <tr key={`${row.sku}-${row.warehouse}`} className="transition-colors duration-150 ease-out hover:bg-white/[0.03]">
                                                <td className="px-5 py-3.5">
                                                    <p className="text-sm font-medium text-white">{row.name}</p>
                                                    <p className="tabular-nums text-xs text-white/50">{row.sku}</p>
                                                </td>
                                                <td className="px-5 py-3.5 text-sm text-white/70">{row.category}</td>
                                                <td className="px-5 py-3.5">
                                                    <p className="text-sm text-white/70">{row.warehouse}</p>
                                                    <p className="text-xs text-white/50">{row.location}</p>
                                                </td>
                                                <td className="px-5 py-3.5 text-right">
                                                    <p className="tabular-nums text-sm font-bold text-white">
                                                        {row.onHand}
                                                    </p>
                                                    <p className="tabular-nums text-xs text-white/50">
                                                        reorder at {row.reorderPoint}
                                                    </p>
                                                    {low && (
                                                        <span className="mt-1 inline-block rounded-sm border border-brand-500/50 px-1.5 py-0.5 text-[0.7rem] font-bold uppercase tracking-wider text-brand-300">
                                                            Low
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-5 py-3.5">
                                                    <span
                                                        className={`tabular-nums text-sm font-semibold ${
                                                            row.lastMove.type === 'out' ? 'text-brand-300' : 'text-white/70'
                                                        }`}
                                                    >
                                                        {MOVEMENT_LABEL[row.lastMove.type](row.lastMove.qty)}
                                                    </span>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </Reveal>
            </div>
        </section>
    );
}