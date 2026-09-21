import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import StaffForm from '@/modules/shared/staff/StaffForm';

const mockApi = vi.hoisted(() => ({
  get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(),
}));
vi.mock('@/services/api', () => ({ default: mockApi }));
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

const rolesResponse = {
  data: {
    roles: [
      { id: 1, name: 'super-admin', description: 'Full access' },
      { id: 2, name: 'staff', description: 'Limited access' },
    ],
  },
};

const renderForm = (props = {}) => {
  const onSubmit = props.onSubmit || vi.fn().mockResolvedValue({});
  const utils = renderWithProviders(
    <StaffForm onSubmit={onSubmit} onCancel={() => {}} embedded {...props} />
  );
  return { ...utils, onSubmit };
};

describe('StaffForm', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockResolvedValue(rolesResponse);
  });

  test('renders role radios (single select)', async () => {
    renderForm();

    expect(await screen.findByRole('radio', { name: /super-admin/ })).toBeInTheDocument();
    expect(screen.getByRole('radio', { name: /staff/ })).toBeInTheDocument();
    expect(screen.getAllByRole('radio')).toHaveLength(2);
  });

  test('selecting a role replaces the previous selection', async () => {
    const staff = { id: 5, name: 'Jane', email: 'jane@example.com', roles: ['staff'], is_active: true };
    renderForm({ staff });

    await screen.findByRole('radio', { name: /super-admin/ });
    expect(screen.getByRole('radio', { name: /staff/ })).toBeChecked();

    fireEvent.click(screen.getByRole('radio', { name: /super-admin/ }));

    expect(screen.getByRole('radio', { name: /super-admin/ })).toBeChecked();
    expect(screen.getByRole('radio', { name: /staff/ })).not.toBeChecked();
  });

  test('warns when changing your own role; Cancel aborts, Continue submits', async () => {
    const staff = { id: 5, name: 'Jane', email: 'jane@example.com', roles: ['staff'], is_active: true };
    const { onSubmit } = renderForm({ staff, isSelf: true });

    await screen.findByRole('radio', { name: /super-admin/ });
    fireEvent.click(screen.getByRole('radio', { name: /super-admin/ }));
    fireEvent.click(screen.getByText('Update'));

    const dialog = await screen.findByRole('dialog');
    expect(dialog).toHaveTextContent(/Your session will close/i);

    fireEvent.click(screen.getByRole('button', { name: /Cancel/i }));
    expect(onSubmit).not.toHaveBeenCalled();

    fireEvent.click(screen.getByText('Update'));
    await screen.findByRole('dialog');
    fireEvent.click(screen.getByRole('button', { name: /Continue/i }));

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({ roles: [1] }));
    });
  });

  test('editing another staff member submits directly without warning', async () => {
    const staff = { id: 6, name: 'Bob', email: 'bob@example.com', roles: ['staff'], is_active: true };
    const { onSubmit } = renderForm({ staff });

    await screen.findByRole('radio', { name: /super-admin/ });
    fireEvent.click(screen.getByRole('radio', { name: /super-admin/ }));
    fireEvent.click(screen.getByText('Update'));

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({ roles: [1] }));
    });
  });

  test('saving your own unchanged role does not warn', async () => {
    const staff = { id: 5, name: 'Jane', email: 'jane@example.com', roles: ['staff'], is_active: true };
    const { onSubmit } = renderForm({ staff, isSelf: true });

    await screen.findByRole('radio', { name: /super-admin/ });
    fireEvent.click(screen.getByText('Update'));

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalled();
    });
  });

  test('locks roles when editing your own Administrator account', async () => {
    const staff = { id: 5, name: 'Jane', email: 'jane@example.com', roles: ['super-admin'], is_active: true };
    const { onSubmit } = renderForm({ staff, isSelf: true, rolesLocked: true, rolesLockedHint: 'Your roles are locked.' });

    await screen.findByRole('radio', { name: /super-admin/ });
    expect(screen.getAllByRole('radio').every((radio) => radio.disabled)).toBe(true);
    expect(screen.getByText('Your roles are locked.')).toBeInTheDocument();

    fireEvent.click(screen.getByText('Update'));

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalled();
    });
    expect(onSubmit.mock.calls[0][0]).not.toHaveProperty('roles');
  });

  test('locks roles when editing the last Administrator account', async () => {
    const staff = { id: 7, name: 'Root', email: 'root@example.com', roles: ['super-admin'], is_active: true };
    renderForm({ staff, rolesLocked: true, rolesLockedHint: 'Last administrator.' });

    await screen.findByRole('radio', { name: /super-admin/ });
    expect(screen.getAllByRole('radio').every((radio) => radio.disabled)).toBe(true);
    expect(screen.getByText('Last administrator.')).toBeInTheDocument();
  });
});