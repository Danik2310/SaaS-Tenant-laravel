import { vi } from 'vitest';
import React from 'react';
import { createEvent, fireEvent, renderWithProviders, screen } from '../test-utils';
import ConfirmDialog from '@/Components/ConfirmDialog';

describe('ConfirmDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('shows the workspace name in bold and requires an exact match', () => {
    const onConfirm = vi.fn();
    const onConfirmationChange = vi.fn();

    const { rerender } = renderWithProviders(
      <ConfirmDialog
        open
        title="Delete Tenant"
        message="This will disable the tenant."
        confirmLabel="Delete"
        confirmationTarget="danikcompani"
        confirmationValue=""
        onConfirmationChange={onConfirmationChange}
        onConfirm={onConfirm}
        onCancel={vi.fn()}
      />,
    );

    expect(screen.getByText('danikcompani').tagName).toBe('STRONG');

    const input = screen.getByTestId('typed-confirmation-input');
    const confirmButton = screen.getByRole('button', { name: 'Delete' });

    expect(confirmButton).toBeDisabled();

    fireEvent.change(input, { target: { value: 'danikcompa' } });
    expect(onConfirmationChange).toHaveBeenCalledWith('danikcompa');

    fireEvent.click(confirmButton);
    expect(onConfirm).not.toHaveBeenCalled();

    rerender(
      <ConfirmDialog
        open
        title="Delete Tenant"
        message="This will disable the tenant."
        confirmLabel="Delete"
        confirmationTarget="danikcompani"
        confirmationValue="danikcompani"
        onConfirmationChange={onConfirmationChange}
        onConfirm={onConfirm}
        onCancel={vi.fn()}
      />,
    );

    expect(screen.getByRole('button', { name: 'Delete' })).toBeEnabled();
    fireEvent.click(screen.getByRole('button', { name: 'Delete' }));
    expect(onConfirm).toHaveBeenCalledTimes(1);
  });

  test('blocks paste into the typed confirmation field', () => {
    renderWithProviders(
      <ConfirmDialog
        open
        title="Delete Tenant"
        confirmationTarget="danikcompani"
        confirmationValue=""
        onConfirmationChange={vi.fn()}
        onConfirm={vi.fn()}
        onCancel={vi.fn()}
      />,
    );

    const event = createEvent.paste(screen.getByTestId('typed-confirmation-input'), {
      clipboardData: { getData: () => 'danikcompani' },
    });

    fireEvent(screen.getByTestId('typed-confirmation-input'), event);
    expect(event.defaultPrevented).toBe(true);
  });

  test('keeps dialogs without typed confirmation unchanged', () => {
    const onConfirm = vi.fn();

    renderWithProviders(
      <ConfirmDialog
        open
        title="Confirm"
        message="Continue?"
        onConfirm={onConfirm}
        onCancel={vi.fn()}
      />,
    );

    expect(screen.queryByTestId('typed-confirmation-input')).not.toBeInTheDocument();

    const confirmButton = screen.getByRole('button', { name: 'Confirm' });
    expect(confirmButton).toBeEnabled();

    fireEvent.click(confirmButton);
    expect(onConfirm).toHaveBeenCalledTimes(1);
  });
});
