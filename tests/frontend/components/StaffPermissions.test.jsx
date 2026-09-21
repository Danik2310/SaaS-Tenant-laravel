import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import StaffPermissions from '@/modules/shared/staff/StaffPermissions';

const mockApi = vi.hoisted(() => ({
  get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(),
}));
vi.mock('@/services/api', () => ({ default: mockApi }));
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

const baseStaffResponse = (overrides = {}) => ({
  data: {
    staff: {
      id: 3,
      name: 'Jane Doe',
      email: 'jane@example.com',
      roles: ['staff'],
      permissions: [],
    },
    available_roles: [
      { id: 1, name: 'super-admin', description: 'Full access', permissions_count: 10, permissions: [] },
      { id: 2, name: 'staff', description: 'Limited access', permissions_count: 3, permissions: [] },
    ],
    is_self: false,
    is_last_super_admin: false,
    ...overrides,
  },
});

const renderDialog = (props = {}) =>
  renderWithProviders(
    <StaffPermissions
      staffId={3}
      staffName="Jane Doe"
      onClose={() => {}}
      onUpdate={() => {}}
      embedded
      {...props}
    />
  );

describe('StaffPermissions', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockResolvedValue(baseStaffResponse());
  });

  test('shows role radios (single select) and no Direct Permissions tab', async () => {
    renderDialog();

    expect(await screen.findByText('super-admin')).toBeInTheDocument();
    expect(screen.getByText('staff')).toBeInTheDocument();
    expect(screen.getByRole('radio', { name: /super-admin/ })).not.toBeChecked();
    expect(screen.getByRole('radio', { name: /staff/ })).toBeChecked();
    expect(screen.queryByText(/Direct Permissions/)).not.toBeInTheDocument();
  });

  test('selecting a role replaces the previous selection', async () => {
    renderDialog();

    await screen.findByText('super-admin');
    expect(screen.getByRole('radio', { name: /staff/ })).toBeChecked();

    fireEvent.click(screen.getByRole('radio', { name: /super-admin/ }));

    expect(screen.getByRole('radio', { name: /super-admin/ })).toBeChecked();
    expect(screen.getByRole('radio', { name: /staff/ })).not.toBeChecked();
  });

  test('saves the selected role_ids', async () => {
    mockApi.post.mockResolvedValue({ data: { staff: {} } });

    renderDialog();

    await screen.findByText('super-admin');
    fireEvent.click(screen.getByRole('radio', { name: /super-admin/ }));
    fireEvent.click(screen.getByText('Save Roles'));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/admin/api/staff/3/roles',
        expect.objectContaining({ role_ids: [1] }),
      );
    });
  });

  test('warns when changing your own role; Cancel aborts and Continue submits', async () => {
    mockApi.get.mockResolvedValue(baseStaffResponse({ is_self: true }));
    mockApi.post.mockResolvedValue({ data: { staff: {} } });

    renderDialog();

    await screen.findByText('super-admin');
    fireEvent.click(screen.getByRole('radio', { name: /super-admin/ }));
    fireEvent.click(screen.getByText('Save Roles'));

    const dialog = await screen.findByRole('dialog');
    expect(dialog).toHaveTextContent(/Your session will close/i);

    fireEvent.click(screen.getByRole('button', { name: /Cancel/i }));
    expect(mockApi.post).not.toHaveBeenCalled();

    fireEvent.click(screen.getByText('Save Roles'));
    await screen.findByRole('dialog');
    fireEvent.click(screen.getByRole('button', { name: /Continue/i }));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/admin/api/staff/3/roles',
        expect.objectContaining({ role_ids: [1] }),
      );
    });
  });

  test('saving your own unchanged role does not warn', async () => {
    mockApi.get.mockResolvedValue(baseStaffResponse({ is_self: true }));
    mockApi.post.mockResolvedValue({ data: { staff: {} } });

    renderDialog();

    await screen.findByText('super-admin');
    fireEvent.click(screen.getByText('Save Roles'));

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalled();
    });
  });

  test('locks roles for own Administrator account and disables Save', async () => {
    mockApi.get.mockResolvedValue(
      baseStaffResponse({
        staff: { id: 3, name: 'Jane Doe', email: 'jane@example.com', roles: ['super-admin'], permissions: [] },
        is_self: true,
        is_last_super_admin: true,
      })
    );

    renderDialog();

    await screen.findByText('super-admin');
    expect(screen.getByRole('radio', { name: /super-admin/ })).toBeDisabled();
    expect(screen.getByRole('radio', { name: /staff/ })).toBeDisabled();
    expect(screen.getByText(/cannot change the roles of your own Administrator account/i)).toBeInTheDocument();
    expect(screen.getByText('Save Roles').closest('button')).toBeDisabled();

    fireEvent.click(screen.getByText('Save Roles'));
    expect(mockApi.post).not.toHaveBeenCalled();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  test('locks roles for the last Administrator account (edited by another admin)', async () => {
    mockApi.get.mockResolvedValue(
      baseStaffResponse({
        staff: { id: 3, name: 'Jane Doe', email: 'jane@example.com', roles: ['super-admin'], permissions: [] },
        is_last_super_admin: true,
      })
    );

    renderDialog();

    await screen.findByText('super-admin');
    expect(screen.getByRole('radio', { name: /super-admin/ })).toBeDisabled();
    expect(screen.getByText(/last Administrator account/i)).toBeInTheDocument();
  });
});