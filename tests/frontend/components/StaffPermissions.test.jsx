import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import StaffPermissions from '@/modules/shared/staff/StaffPermissions';

const mockApi = vi.hoisted(() => ({
  get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(),
}));
vi.mock('@/services/api', () => ({ default: mockApi }));
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

const staffResponse = {
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
  },
};

describe('StaffPermissions', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockResolvedValue(staffResponse);
  });

  test('shows role checkboxes and no Direct Permissions tab', async () => {
    renderWithProviders(
      <StaffPermissions staffId={3} staffName="Jane Doe" onClose={() => {}} onUpdate={() => {}} embedded />
    );

    expect(await screen.findByText('super-admin')).toBeInTheDocument();
    expect(screen.getByText('staff')).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /super-admin/ })).not.toBeChecked();
    expect(screen.getByRole('checkbox', { name: /staff/ })).toBeChecked();
    expect(screen.queryByText(/Direct Permissions/)).not.toBeInTheDocument();
  });

  test('saves the selected role_ids', async () => {
    mockApi.post.mockResolvedValue({ data: { staff: {} } });

    renderWithProviders(
      <StaffPermissions staffId={3} staffName="Jane Doe" onClose={() => {}} onUpdate={() => {}} embedded />
    );

    await screen.findByText('super-admin');
    fireEvent.click(screen.getByRole('checkbox', { name: /super-admin/ }));
    fireEvent.click(screen.getByText('Save Roles'));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/admin/api/staff/3/roles',
        expect.objectContaining({ role_ids: expect.arrayContaining([1, 2]) }),
      );
    });
  });
});
