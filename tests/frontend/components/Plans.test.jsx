import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import Plans from '@/modules/billing/Plans';

const mockApi = vi.hoisted(() => ({
  get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(),
}));
vi.mock('@/services/api', () => ({ default: mockApi }));

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
import { toast } from 'sonner';

const mockPlans = [
  {
    id: 1, name: 'Free', slug: 'free', price: 0, status: 'active',
    duration_months: null, max_users: 5, max_storage: 100, max_warehouses: 1,
    max_categories: 5, max_products: 50,
    features: ['basic_reports'], created_at: '2026-07-01T00:00:00.000000Z',
  },
  {
    id: 2, name: 'Pro', slug: 'pro', price: 29.99, status: 'active',
    duration_months: 12, max_users: 50, max_storage: 1024, max_warehouses: 5,
    max_categories: 50, max_products: 1000,
    features: ['advanced', 'custom_domain'], created_at: '2026-07-02T00:00:00.000000Z',
  },
];

const listResponse = {
  data: {
    plans: mockPlans,
    feature_definitions: {
      advanced: { key: 'advanced', label: 'Advanced', description: '', is_active: true },
      basic_reports: { key: 'basic_reports', label: 'Basic Reports', description: '', is_active: true },
      custom_domain: { key: 'custom_domain', label: 'Custom Domain', description: '', is_active: true },
    },
    meta: { current_page: 1, last_page: 1, per_page: 5, total: mockPlans.length },
  },
};

const summaryResponse = {
  data: {
    summary: {
      total_tenants: 3,
      avg_users: 12,
      max_users: 50,
      total_storage_mb: 2048,
      avg_storage_mb: 683,
      max_storage_mb: 1024,
      avg_products: 120,
      max_products: 1000,
      total_products: 360,
      server_disk_total_gb: null,
      server_disk_used_gb: null,
      server_disk_free_gb: null,
      server_disk_pct: null,
      server_disk_label: null,
    },
  },
};

describe('Plans', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockImplementation((url) => {
      if (String(url).includes('/admin/api/resource-usage/summary')) return Promise.resolve(summaryResponse);
      if (String(url).includes('/admin/api/plans')) return Promise.resolve(listResponse);
      return Promise.reject(new Error('Unexpected request: ' + url));
    });
  });

  test('renders Edit and Delete actions for every plan row', async () => {
    renderWithProviders(<Plans />);

    await screen.findByText('Free');
    expect(screen.getByText('Pro')).toBeInTheDocument();

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith(expect.stringContaining('/admin/api/plans'));
    });

    expect(screen.getAllByLabelText('Edit')).toHaveLength(2);
    expect(screen.getAllByLabelText('Delete')).toHaveLength(2);
  });

  test('opens the edit dialog pre-filled with the selected plan', async () => {
    renderWithProviders(<Plans />);

    await screen.findByText('Free');
    fireEvent.click(screen.getAllByLabelText('Edit')[0]);

    expect(await screen.findByText('Edit Plan')).toBeInTheDocument();
    expect(screen.getByLabelText(/^Plan Name/)).toHaveValue('Free');
  });

  test('submits edits for an existing plan', async () => {
    mockApi.put.mockResolvedValueOnce({ data: { plan: mockPlans[1] } });

    renderWithProviders(<Plans />);

    await screen.findByText('Free');
    fireEvent.click(screen.getAllByLabelText('Edit')[1]);

    await screen.findByText('Edit Plan');
    fireEvent.change(screen.getByLabelText(/^Plan Name/), { target: { value: 'Pro Max' } });
    fireEvent.click(screen.getByText('Save Changes'));

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        '/admin/api/plans/2',
        expect.objectContaining({ name: 'Pro Max', slug: 'pro' }),
      );
    });
  });

  test('opens the delete confirmation dialog', async () => {
    renderWithProviders(<Plans />);

    await screen.findByText('Free');
    fireEvent.click(screen.getAllByLabelText('Delete')[1]);

    expect(await screen.findByText('Delete Plan')).toBeInTheDocument();
    expect(screen.getByText(/Are you sure you want to delete "Pro"\?/)).toBeInTheDocument();
  });

  test('deletes a plan after confirmation', async () => {
    mockApi.delete.mockResolvedValueOnce({});

    renderWithProviders(<Plans />);

    await screen.findByText('Free');
    fireEvent.click(screen.getAllByLabelText('Delete')[1]);

    await screen.findByText('Delete Plan');
    fireEvent.click(screen.getByText('Delete'));

    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalledWith('/admin/api/plans/2');
    });
  });

  test('surfaces server rejection when deleting a plan in use', async () => {
    mockApi.delete.mockRejectedValueOnce({
      response: { data: { message: 'Cannot delete a plan assigned to 2 tenant(s). Reassign tenants first.' } },
    });

    renderWithProviders(<Plans />);

    await screen.findByText('Free');
    fireEvent.click(screen.getAllByLabelText('Delete')[1]);

    await screen.findByText('Delete Plan');
    fireEvent.click(screen.getByText('Delete'));

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledWith('Cannot delete a plan assigned to 2 tenant(s). Reassign tenants first.');
    });
  });

  test('refetches feature definitions when switching back to the Plans tab', async () => {
    renderWithProviders(<Plans />);

    await screen.findByText('Free');

    const plansCalls = () => mockApi.get.mock.calls.filter(([url]) => String(url).includes('/admin/api/plans')).length;
    expect(plansCalls()).toBe(1);

    fireEvent.click(screen.getByText('Feature Flags'));
    fireEvent.click(screen.getByText('Plans'));

    await waitFor(() => {
      expect(plansCalls()).toBe(2);
    });
  });
});
