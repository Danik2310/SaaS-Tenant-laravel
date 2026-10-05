/**
 * Inventory demo data.
 *
 * Deliberately kept out of `content.js`: that file holds the marketing copy and
 * is verified byte-identical against the approved source. The demo is an
 * illustration of the product, not part of the site's voice, so it lives here.
 *
 * Field names mirror the real tenant schema 1:1 so a developer evaluating the
 * product recognises the shape they will build against:
 *
 *   products            → sku (unique), name, price, category_id → `category`
 *   categories          → name                              → `category`
 *   warehouses          → name, location                    → `warehouse`, `location`
 *   inventory_movements → type (in|out|adjustment), quantity → `lastMove`
 *
 * `onHand` is the running balance of movements for that product at that
 * warehouse, and `reorderPoint` is the threshold the demo flags against.
 */
export const DEMO_ROWS = [
    {
        sku: 'AUD-0142',
        name: 'Wireless earbuds',
        category: 'Audio',
        price: '24.00',
        warehouse: 'North warehouse',
        location: 'Leeds',
        onHand: 342,
        reorderPoint: 200,
        lastMove: { type: 'in', qty: 18 },
    },
    {
        sku: 'BAG-0087',
        name: 'Canvas tote',
        category: 'Bags',
        price: '12.50',
        warehouse: 'North warehouse',
        location: 'Leeds',
        onHand: 96,
        reorderPoint: 150,
        lastMove: { type: 'out', qty: 12 },
    },
    {
        sku: 'HOM-0231',
        name: 'Linen throw',
        category: 'Home',
        price: '38.00',
        warehouse: 'North warehouse',
        location: 'Leeds',
        onHand: 204,
        reorderPoint: 120,
        lastMove: { type: 'in', qty: 7 },
    },
    {
        sku: 'KIT-0098',
        name: 'Cast iron pan',
        category: 'Kitchen',
        price: '31.00',
        warehouse: 'South warehouse',
        location: 'Bristol',
        onHand: 58,
        reorderPoint: 40,
        lastMove: { type: 'in', qty: 24 },
    },
    {
        sku: 'OUT-0304',
        name: 'Trail backpack',
        category: 'Outdoor',
        price: '74.00',
        warehouse: 'Central warehouse',
        location: 'Sheffield',
        onHand: 41,
        reorderPoint: 90,
        lastMove: { type: 'out', qty: 9 },
    },
    {
        sku: 'AUD-0165',
        name: 'Desk microphone',
        category: 'Audio',
        price: '89.00',
        warehouse: 'South warehouse',
        location: 'Bristol',
        onHand: 127,
        reorderPoint: 60,
        lastMove: { type: 'adjustment', qty: 3 },
    },
    {
        sku: 'BAG-0102',
        name: 'Leather weekender',
        category: 'Bags',
        price: '145.00',
        warehouse: 'Central warehouse',
        location: 'Sheffield',
        onHand: 73,
        reorderPoint: 60,
        lastMove: { type: 'in', qty: 4 },
    },
    {
        sku: 'HOM-0248',
        name: 'Ceramic vase',
        category: 'Home',
        price: '27.50',
        warehouse: 'Central warehouse',
        location: 'Sheffield',
        onHand: 19,
        reorderPoint: 35,
        lastMove: { type: 'out', qty: 2 },
    },
    {
        sku: 'KIT-0111',
        name: 'Espresso cups',
        category: 'Kitchen',
        price: '16.00',
        warehouse: 'North warehouse',
        location: 'Leeds',
        onHand: 340,
        reorderPoint: 200,
        lastMove: { type: 'in', qty: 31 },
    },
    {
        sku: 'OUT-0319',
        name: 'Headlamp',
        category: 'Outdoor',
        price: '22.00',
        warehouse: 'South warehouse',
        location: 'Bristol',
        onHand: 156,
        reorderPoint: 80,
        lastMove: { type: 'in', qty: 12 },
    },
    {
        sku: 'AUD-0173',
        name: 'Bluetooth speaker',
        category: 'Audio',
        price: '58.00',
        warehouse: 'Central warehouse',
        location: 'Sheffield',
        onHand: 88,
        reorderPoint: 100,
        lastMove: { type: 'out', qty: 6 },
    },
    {
        sku: 'HOM-0261',
        name: 'Wool blanket',
        category: 'Home',
        price: '64.00',
        warehouse: 'South warehouse',
        location: 'Bristol',
        onHand: 245,
        reorderPoint: 150,
        lastMove: { type: 'in', qty: 15 },
    },
    {
        sku: 'BAG-0118',
        name: 'Card holder',
        category: 'Bags',
        price: '9.00',
        warehouse: 'North warehouse',
        location: 'Leeds',
        onHand: 512,
        reorderPoint: 250,
        lastMove: { type: 'adjustment', qty: 1 },
    },
];

// Filter options are derived from the rows rather than hand-maintained, so an
// option can never exist with nothing behind it.
export const DEMO_CATEGORIES = [...new Set(DEMO_ROWS.map((row) => row.category))].sort();

export const DEMO_WAREHOUSES = [...new Set(DEMO_ROWS.map((row) => row.warehouse))].sort();

export const MOVEMENT_LABEL = {
    in: (qty) => `+${qty} in`,
    out: (qty) => `−${qty} out`,
    adjustment: (qty) => `±${qty} adj`,
};