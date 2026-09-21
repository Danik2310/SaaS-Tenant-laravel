import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, waitFor } from '../test-utils';
import StaffList from '@/modules/shared/staff/StaffList';

const mockApi = vi.hoisted(() => ({
  get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(),
}));
vi.mock('@/services/api', () => ({ default: mockApi }));
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

const { useAuthContextMock } = vi.hoisted(() => ({ useAuthContextMock: vi.fn() }));
vi.mock('@/context/AuthContext', () => ({ useAuthContext: () => useAuthContextMock() }));

const staffRows = [
  {
    id: 1,
    name: 'Root Admin',
    email: 'root@example.com',
    is_active: true,
    is_main_admin: true,
    roles: ['super-admin'],
    permissions_count: 10,
    permissions: [],
    created_at: '2025-01-01 00:00:00',
  },
  {
    id: 2,
    name: 'John Doe',
    email: 'john@example.com',
    is_active: true,
    is_main_admin: false,
    roles: ['staff'],
    permissions_count: 2,
    permissions: [],
    created_at: '2025-02-01 00:00:00',
  },
];

const listResponse = { data: { staff: staffRows, total: 2 } };

describe('StaffList main administrator protection', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    useAuthContextMock.mockReturnValue({
      user: { id: 999, name: 'System Admin' },
      permissions: ['edit staff', 'delete staff'],
    });
    mockApi.get.mockResolvedValue(listResponse);
  });

  test('shows a Main Admin badge for the main administrator row', async () => {
    renderWithProviders(<StaffList />);

    await waitFor(() => expect(screen.getByText('Root Admin')).toBeInTheDocument());

    expect(screen.getByText('Main Admin')).toBeInTheDocument();
    expect(screen.getByText('John Doe')).toBeInTheDocument();
  });

  test('hides action buttons for the protected main admin row but keeps them for other rows', async () => {
    renderWithProviders(<StaffList />);

    await waitFor(() => expect(screen.getByText('Root Admin')).toBeInTheDocument());

    // Badge lock icon + protected-action lock icon for the main admin row.
    expect(screen.getAllByTestId('LockIcon').length).toBeGreaterThanOrEqual(2);

    // Only the non-protected row keeps its action buttons.
    expect(screen.getAllByTestId('EditIcon')).toHaveLength(1);
    expect(screen.getAllByTestId('DeleteIcon')).toHaveLength(1);
    expect(screen.getAllByTestId('BlockIcon')).toHaveLength(1);
  });

  test('keeps actions on the main admin row when it is the authenticated user', async () => {
    useAuthContextMock.mockReturnValue({
      user: { id: 1, name: 'Root Admin' },
      permissions: ['edit staff', 'delete staff'],
    });

    renderWithProviders(<StaffList />);

    await waitFor(() => expect(screen.getByText('Root Admin')).toBeInTheDocument());

    // Only the badge lock icon; the row is their own, so actions remain.
    expect(screen.getAllByTestId('LockIcon')).toHaveLength(1);
    expect(screen.getAllByTestId('EditIcon')).toHaveLength(2);
    expect(screen.getAllByTestId('DeleteIcon')).toHaveLength(2);
  });
});