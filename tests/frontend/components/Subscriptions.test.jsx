import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import Subscriptions from '@/modules/billing/Subscriptions';

const mockApi = vi.hoisted(() => ({
  get: vi.fn(),
}));
vi.mock('@/services/api', () => ({ default: mockApi }));

vi.mock('sonner', () => ({
  toast: { success: vi.fn(), error: vi.fn() },
}));

const mockSubscriptions = [
  {
    id: 1,
    tenant_id: 1,
    plan_id: 1,
    tenant_name: 'Acme Corp',
    tenant_status: 'active',
    tenant_actual_status: 'Active',
    plan_name: 'Pro',
    plan_price: '29.00',
    plan_slug: 'pro',
    status: 'active',
    starts_at: '2025-01-01',
    ends_at: '2026-01-01',
    created_at: '2025-01-01 00:00:00',
  },
  {
    id: 2,
    tenant_id: 2,
    plan_id: 2,
    tenant_name: 'Globex Inc',
    tenant_status: 'active',
    tenant_actual_status: 'Active',
    plan_name: 'Enterprise',
    plan_price: '99.00',
    plan_slug: 'enterprise',
    status: 'active',
    starts_at: '2025-03-15',
    ends_at: '2026-03-15',
    created_at: '2025-03-15 00:00:00',
  },
  {
    id: 3,
    tenant_id: 3,
    plan_id: 1,
    tenant_name: 'Tienda XYZ',
    tenant_status: 'deleted',
    tenant_actual_status: 'Deleted',
    plan_name: 'Pro',
    plan_price: '29.00',
    plan_slug: 'pro',
    status: 'active',
    starts_at: '2024-06-01',
    ends_at: '2025-06-01',
    created_at: '2024-06-01 00:00:00',
  },
];

const mockPlans = [
  { id: 1, name: 'Pro', slug: 'pro' },
  { id: 2, name: 'Enterprise', slug: 'enterprise' },
];

describe('Subscriptions', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockImplementation((url) => {
      if (url.includes('/subscriptions') && !url.includes('/payments')) {
        return Promise.resolve({ data: { subscriptions: mockSubscriptions, total: 3 } });
      }
      if (url.includes('/plans-list')) {
        return Promise.resolve({ data: { plans: mockPlans } });
      }
      if (url.includes('/payments')) {
        return Promise.resolve({ data: { payments: [], subscription: { id: 1, tenant_name: 'Acme Corp', plan_name: 'Pro' } } });
      }
      return Promise.resolve({ data: {} });
    });
  });

  it('renders subscription heading', async () => {
    renderWithProviders(<Subscriptions />);
    await waitFor(() => {
      expect(screen.getByText('Subscriptions')).toBeInTheDocument();
    });
  });

  it('renders subscription table rows from API data', async () => {
    renderWithProviders(<Subscriptions />);

    await waitFor(() => {
      expect(screen.getAllByText('Pro').length).toBeGreaterThan(0);
    });
    expect(screen.getByText('Enterprise')).toBeInTheDocument();
  });

  it('displays tenant names', async () => {
    renderWithProviders(<Subscriptions />);

    await waitFor(() => {
      expect(screen.getByText('Acme Corp')).toBeInTheDocument();
    });
    expect(screen.getByText('Globex Inc')).toBeInTheDocument();
  });

  it('displays plan prices', async () => {
    renderWithProviders(<Subscriptions />);

    await waitFor(() => {
      expect(screen.getAllByText('$29.00').length).toBeGreaterThan(0);
    });
    expect(screen.getByText('$99.00')).toBeInTheDocument();
  });

  it('displays subscription status chips', async () => {
    renderWithProviders(<Subscriptions />);

    await waitFor(() => {
      const statusChips = screen.getAllByText('active');
      expect(statusChips.length).toBeGreaterThan(0);
    });
  });

  it('does not show New Subscription button', async () => {
    renderWithProviders(<Subscriptions />);

    await waitFor(() => {
      expect(screen.getByText('Subscriptions')).toBeInTheDocument();
    });
    expect(screen.queryByText('+ New Subscription')).not.toBeInTheDocument();
  });

  it('renders error alert when API call fails', async () => {
    mockApi.get.mockImplementation((url) => {
      if (url.includes('/subscriptions')) {
        return Promise.reject(new Error('Network error'));
      }
      return Promise.resolve({ data: {} });
    });

    renderWithProviders(<Subscriptions />);

    await waitFor(() => {
      expect(screen.getByText('Failed to fetch subscriptions')).toBeInTheDocument();
    });
  });

  it('shows retry button on error', async () => {
    mockApi.get.mockImplementation((url) => {
      if (url.includes('/subscriptions')) {
        return Promise.reject(new Error('Network error'));
      }
      return Promise.resolve({ data: {} });
    });

    renderWithProviders(<Subscriptions />);

    await waitFor(() => {
      expect(screen.getByText('Retry')).toBeInTheDocument();
    });
  });

  it('renders row action buttons', async () => {
    renderWithProviders(<Subscriptions />);

    await waitFor(() => {
      expect(screen.getAllByText('Pro').length).toBeGreaterThan(0);
    });

    const viewButtons = screen.getAllByTestId('view-tenant-btn');
    expect(viewButtons.length).toBeGreaterThan(0);

    const paymentButtons = screen.getAllByTestId('payment-history-btn');
    expect(paymentButtons.length).toBeGreaterThan(0);
  });

  it('opens payment history dialog when payment button is clicked', async () => {
    renderWithProviders(<Subscriptions />);

    await waitFor(() => {
      expect(screen.getAllByText('Pro').length).toBeGreaterThan(0);
    });

    const paymentButtons = screen.getAllByTestId('payment-history-btn');
    fireEvent.click(paymentButtons[0]);

    await waitFor(() => {
      expect(screen.getByText('Payment History')).toBeInTheDocument();
    });
  });

  it('shows Record Payment button in dialog', async () => {
    renderWithProviders(<Subscriptions />);

    await waitFor(() => {
      expect(screen.getAllByText('Pro').length).toBeGreaterThan(0);
    });

    const paymentButtons = screen.getAllByTestId('payment-history-btn');
    fireEvent.click(paymentButtons[0]);

    await waitFor(() => {
      expect(screen.getByText('Record Payment')).toBeInTheDocument();
    });
  });

  it('shows empty state when no payments exist', async () => {
    renderWithProviders(<Subscriptions />);

    await waitFor(() => {
      expect(screen.getAllByText('Pro').length).toBeGreaterThan(0);
    });

    const paymentButtons = screen.getAllByTestId('payment-history-btn');
    fireEvent.click(paymentButtons[0]);

    await waitFor(() => {
      expect(screen.getByText('No payments recorded yet.')).toBeInTheDocument();
    });
  });

  it('displays deleted tenant name with Deleted badge', async () => {
    renderWithProviders(<Subscriptions />);

    await waitFor(() => {
      expect(screen.getByText(/Tienda XYZ/)).toBeInTheDocument();
    });

    expect(screen.getByText(/Deleted/)).toBeInTheDocument();
  });

  it('enables View Tenant button for deleted tenants', async () => {
    renderWithProviders(<Subscriptions />);

    await waitFor(() => {
      expect(screen.getByText(/Tienda XYZ/)).toBeInTheDocument();
    });

    const viewButtons = screen.getAllByTestId('view-tenant-btn');
    const deletedRowButton = viewButtons[2];
    expect(deletedRowButton).not.toBeDisabled();
  });
});
