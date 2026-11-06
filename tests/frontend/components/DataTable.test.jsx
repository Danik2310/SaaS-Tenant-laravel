import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import DataTable from '@/Components/DataTable';

const columns = [{ accessorKey: 'name', header: 'Name' }];

const rows = [
    { id: 1, name: 'Acme Corp' },
    { id: 2, name: 'Beta Ltd' },
];

function actionButton(testId, index = 0) {
    const icon = screen.getAllByTestId(testId)[index];
    const button = icon.closest('button');
    if (!button) {
        throw new Error(`${testId} is not rendered inside an IconButton`);
    }
    return button;
}

describe('DataTable row actions', () => {
    test('opens the "More actions" menu for the row that was clicked', async () => {
        const onInspect = vi.fn();
        const onArchive = vi.fn();
        const rowMenuActions = [
            { label: 'Inspect', onClick: onInspect },
            { divider: true },
            { label: 'Archive', onClick: onArchive },
        ];

        renderWithProviders(
            <DataTable columns={columns} data={rows} rowMenuActions={rowMenuActions} />,
        );

        fireEvent.click(actionButton('MoreVertIcon', 1));

        // Only the clicked row's menu is mounted, so a per-row menu that reads a
        // stale `menuAnchor` would leave these items unrendered entirely.
        await waitFor(() => expect(screen.getAllByRole('menuitem')).toHaveLength(2));
        expect(screen.getByText('Inspect')).toBeInTheDocument();

        fireEvent.click(screen.getByText('Archive'));

        expect(onArchive).toHaveBeenCalledWith(rows[1]);
        expect(onInspect).not.toHaveBeenCalled();
        await waitFor(() => expect(screen.queryAllByRole('menuitem')).toHaveLength(0));
    });

    test('uses the newest action handlers after a re-render', () => {
        const firstOnView = vi.fn();
        const secondOnView = vi.fn();

        const { rerender } = renderWithProviders(
            <DataTable columns={columns} data={rows} onView={firstOnView} />,
        );

        fireEvent.click(actionButton('VisibilityIcon'));
        expect(firstOnView).toHaveBeenCalledWith(rows[0]);

        rerender(<DataTable columns={columns} data={rows} onView={secondOnView} />);

        fireEvent.click(actionButton('VisibilityIcon'));

        expect(secondOnView).toHaveBeenCalledWith(rows[0]);
        expect(firstOnView).toHaveBeenCalledTimes(1);
    });
});
