import InventoryDemo from '@/Components/Marketing/InventoryDemo';
import { DEMO_ROWS } from '@/Components/Marketing/inventoryDemoData';
import { render, screen, within, fireEvent } from '../test-utils';

const renderDemo = () => render(<InventoryDemo />);

const table = () => screen.getByRole('table');

const bodyRows = () => within(table()).getAllByRole('row').slice(1);

const productNames = () =>
    bodyRows()
        .map((row) => within(row).getAllByRole('cell')[0]?.querySelector('p'))
        .filter(Boolean)
        .map((name) => name.textContent);

const onHandValues = () =>
    bodyRows().map((row) => Number(within(row).getAllByRole('cell')[3].querySelector('p').textContent));

// Anchored so it cannot also match the table caption, which repeats the totals
// inside its own sentence.
const counter = () => screen.getByText(/^\d+ of \d+ products$/).textContent.trim();

const columnHeader = (name) => within(table()).getByRole('columnheader', { name });

describe('InventoryDemo', () => {
    test('lists every row sorted by product name on first paint', () => {
        renderDemo();

        expect(productNames()).toHaveLength(DEMO_ROWS.length);
        expect(productNames()[0]).toBe('Bluetooth speaker');
        expect(counter()).toBe(`${DEMO_ROWS.length} of ${DEMO_ROWS.length} products`);
    });

    test('sorts by product name ascending without mutating the source data', () => {
        renderDemo();

        const names = productNames();
        expect(names).toEqual([...names].sort((a, b) => a.localeCompare(b)));
        expect(DEMO_ROWS.map((row) => row.name)).toContain('Wireless earbuds');
    });

    test('searches by product name', () => {
        renderDemo();

        fireEvent.change(screen.getByLabelText('Search by product name or SKU'), { target: { value: 'blanket' } });

        expect(productNames()).toEqual(['Wool blanket']);
        expect(counter()).toBe(`1 of ${DEMO_ROWS.length} products`);
    });

    test('searches by SKU, ignoring case and surrounding whitespace', () => {
        renderDemo();

        fireEvent.change(screen.getByLabelText('Search by product name or SKU'), { target: { value: '  aud-0142 ' } });

        expect(productNames()).toEqual(['Wireless earbuds']);
    });

    test('filters by category and reflects the active chip with aria-pressed', () => {
        renderDemo();

        const chip = screen.getByRole('button', { name: 'Outdoor' });
        expect(chip).toHaveAttribute('aria-pressed', 'false');

        fireEvent.click(chip);

        expect(chip).toHaveAttribute('aria-pressed', 'true');
        expect(productNames()).toEqual(['Headlamp', 'Trail backpack']);
        expect(counter()).toBe(`2 of ${DEMO_ROWS.length} products`);
    });

    test('filters by warehouse', () => {
        renderDemo();

        fireEvent.change(screen.getByLabelText('Filter by warehouse'), { target: { value: 'Central warehouse' } });

        expect(productNames()).toHaveLength(4);
        expect(productNames()).toEqual(['Bluetooth speaker', 'Ceramic vase', 'Leather weekender', 'Trail backpack']);
    });

    test('shows only rows below their reorder point when low stock is toggled', () => {
        renderDemo();

        const toggle = screen.getByRole('button', { name: 'Low stock only' });
        expect(toggle).toHaveAttribute('aria-pressed', 'false');

        fireEvent.click(toggle);

        expect(toggle).toHaveAttribute('aria-pressed', 'true');
        expect(productNames()).toEqual(['Bluetooth speaker', 'Canvas tote', 'Ceramic vase', 'Trail backpack']);
        expect(counter()).toBe(`4 of ${DEMO_ROWS.length} products`);
    });

    test('combines category, warehouse and low stock filters', () => {
        renderDemo();

        fireEvent.click(screen.getByRole('button', { name: 'Audio' }));
        fireEvent.change(screen.getByLabelText('Filter by warehouse'), { target: { value: 'Central warehouse' } });
        fireEvent.click(screen.getByRole('button', { name: 'Low stock only' }));

        expect(productNames()).toEqual(['Bluetooth speaker']);
    });

    test('resets to every row when the All category chip is selected', () => {
        renderDemo();

        fireEvent.click(screen.getByRole('button', { name: 'Kitchen' }));
        expect(productNames()).toHaveLength(2);

        fireEvent.click(screen.getByRole('button', { name: 'All' }));

        expect(productNames()).toHaveLength(DEMO_ROWS.length);
        expect(screen.getByRole('button', { name: 'All' })).toHaveAttribute('aria-pressed', 'true');
    });

    test('sorts on hand numerically and toggles direction on a second click', () => {
        renderDemo();

        fireEvent.click(screen.getByRole('button', { name: 'On hand' }));

        const ascending = onHandValues();
        expect(ascending).toEqual([...ascending].sort((a, b) => a - b));
        expect(ascending[0]).toBe(19);

        fireEvent.click(screen.getByRole('button', { name: 'On hand' }));

        expect(onHandValues()).toEqual([...ascending].reverse());
    });

    test('marks only the sorted column with aria-sort', () => {
        renderDemo();

        expect(columnHeader('Product')).toHaveAttribute('aria-sort', 'ascending');
        expect(columnHeader('On hand')).not.toHaveAttribute('aria-sort');

        fireEvent.click(screen.getByRole('button', { name: 'Category' }));

        expect(columnHeader('Category')).toHaveAttribute('aria-sort', 'ascending');
        expect(columnHeader('Product')).not.toHaveAttribute('aria-sort');
    });

    test('keeps the sort order inside the active filter', () => {
        renderDemo();

        fireEvent.click(screen.getByRole('button', { name: 'Bags' }));
        fireEvent.click(screen.getByRole('button', { name: 'On hand' }));

        expect(onHandValues()).toEqual([73, 96, 512]);
        expect(counter()).toBe('3 of 13 products');
    });

    test('announces an empty result instead of rendering a blank table', () => {
        renderDemo();

        fireEvent.change(screen.getByLabelText('Search by product name or SKU'), { target: { value: 'no-such-product' } });

        expect(screen.getByText('No products match these filters.')).toBeInTheDocument();
        expect(counter()).toBe(`0 of ${DEMO_ROWS.length} products`);
    });

    test('exposes the table as a keyboard reachable region with a caption', () => {
        renderDemo();

        const region = screen.getByRole('region', { name: 'Stock levels by product and warehouse' });
        expect(region).toHaveAttribute('tabindex', '0');
        expect(within(table()).getByText(/sorted by name/)).toBeInTheDocument();
    });

    test('labels every control for assistive technology', () => {
        renderDemo();

        expect(screen.getByLabelText('Search by product name or SKU')).toBeInTheDocument();
        expect(screen.getByLabelText('Filter by warehouse')).toBeInTheDocument();
        expect(screen.getByText('Low stock only').closest('button')).toHaveAttribute('aria-pressed', 'false');
        expect(screen.getByText(/^\d+ of \d+ products$/)).toHaveAttribute('aria-live', 'polite');
    });
});