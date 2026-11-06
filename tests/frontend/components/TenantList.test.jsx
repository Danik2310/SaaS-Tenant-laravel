import { vi } from 'vitest';
import React from 'react';
import axios from 'axios';
import { renderWithProviders, screen, fireEvent, waitFor, within } from '../test-utils';
import TenantList from '@/modules/tenants/TenantList';

const mockApi = vi.hoisted(() => ({
  get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(),
}));
vi.mock('@/services/api', () => ({ default: mockApi }));

const { useAuthContextMock } = vi.hoisted(() => ({ useAuthContextMock: vi.fn() }));
vi.mock('@/context/AuthContext', () => ({ useAuthContext: () => useAuthContextMock() }));

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
import { toast } from 'sonner';

const tenantRows = [
  {
    id: 7,
    reference_id: 'T-007',
    name: 'Acme Corp',
    email: 'ops@acme.test',
    domain: 'acme.test',
    status: 'Active',
    plan_name: 'Pro',
    subscription_ends_at: '2027-01-01T00:00:00Z',
    subscription_plan_name: 'Pro',
    subscription_duration_months: 12,
    has_active_subscription: true,
    created_at: '2025-01-15T10:00:00Z',
    is_deleted: false,
    trial_ends_at: null,
    trial_has_expired: false,
  },
  {
    id: 8,
    reference_id: 'T-008',
    name: 'Beta Ltd',
    email: 'ops@beta.test',
    domain: 'beta.test',
    status: 'Active',
    plan_name: 'Basic',
    subscription_ends_at: null,
    subscription_plan_name: null,
    subscription_duration_months: null,
    has_active_subscription: false,
    created_at: '2025-02-20T10:00:00Z',
    is_deleted: false,
    trial_ends_at: null,
    trial_has_expired: false,
  },
];

const deletedRow = {
  id: 9,
  reference_id: 'T-009',
  name: 'Deleted Tenant',
  email: 'gone@example.test',
  domain: 'gone.test',
  status: 'Suspended',
  plan_name: null,
  subscription_ends_at: null,
  subscription_plan_name: null,
  subscription_duration_months: null,
  has_active_subscription: false,
  created_at: '2024-12-01T10:00:00Z',
  is_deleted: true,
  trial_ends_at: null,
  trial_has_expired: false,
};

const listResponse = { data: { tenants: tenantRows, total: tenantRows.length } };

describe('TenantList', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockReset();
    mockApi.get.mockResolvedValue(listResponse);
    // Only 'view tenants' keeps the plans-list request from firing, so the
    // tenants endpoint is the single caller of api.get.
    useAuthContextMock.mockReturnValue({ permissions: ['view tenants'] });
  });

  test('selects rows by tenant id rather than by row index', async () => {
    const onSelectionChange = vi.fn();

    renderWithProviders(<TenantList onSelectionChange={onSelectionChange} />);

    const row = (await screen.findByText('Acme Corp')).closest('tr');
    fireEvent.click(within(row).getByRole('checkbox'));

    await waitFor(() => {
      expect(onSelectionChange).toHaveBeenLastCalledWith(new Set(['7']));
    });
  });

  test('aborts the in-flight request when the list unmounts', async () => {
    let capturedConfig;
    mockApi.get.mockImplementation((url, config) => {
      capturedConfig = config;
      return new Promise(() => {});
    });

    const { unmount } = renderWithProviders(<TenantList />);

    await waitFor(() => expect(capturedConfig?.signal).toBeDefined());
    expect(capturedConfig.signal.aborted).toBe(false);

    unmount();

    expect(capturedConfig.signal.aborted).toBe(true);
  });

  test('a superseded request is aborted silently while the new one renders', async () => {
    mockApi.get
      .mockImplementationOnce((url, config) => new Promise((resolve, reject) => {
        config.signal.addEventListener('abort', () => reject(new axios.CanceledError('canceled')));
      }))
      .mockResolvedValueOnce({ data: { tenants: [deletedRow], total: 1 } });

    renderWithProviders(<TenantList />);

    await waitFor(() => expect(mockApi.get).toHaveBeenCalledTimes(1));

    fireEvent.click(screen.getByRole('switch', { name: /show deleted/i }));

    expect(await screen.findByText('Deleted Tenant')).toBeInTheDocument();
    expect(mockApi.get.mock.calls[0][1].signal.aborted).toBe(true);
    expect(toast.error).not.toHaveBeenCalled();
    expect(screen.queryByText('Failed to fetch tenants')).not.toBeInTheDocument();
  });

  test('a genuine failure still surfaces the error and the retry action', async () => {
    mockApi.get.mockRejectedValueOnce(new Error('network down'));

    renderWithProviders(<TenantList />);

    expect(await screen.findByText('Failed to fetch tenants')).toBeInTheDocument();
    expect(toast.error).toHaveBeenCalledWith('Failed to fetch tenants');
    expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument();
  });
});
