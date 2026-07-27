import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, waitFor } from '../test-utils';
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
    plan_name: 'Enterprise',
    plan_price: '99.00',
    plan_slug: 'enterprise',
    status: 'active',
    starts_at: '2025-03-15',
    ends_at: '2026-03-15',
    created_at: '2025-03-15 00:00:00',
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
      if (url.includes('/subscriptions')) {
        return Promise.resolve({ data: { subscriptions: mockSubscriptions, total: 2 } });
      }
      if (url.includes('/plans-list')) {
        return Promise.resolve({ data: { plans: mockPlans } });
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
      expect(screen.getByText('Pro')).toBeInTheDocument();
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
      expect(screen.getByText('$29.00')).toBeInTheDocument();
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
});
