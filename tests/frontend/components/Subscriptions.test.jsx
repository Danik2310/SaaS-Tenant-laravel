import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import Subscriptions from '@/modules/landlord/subscriptions/Subscriptions';

const mockApi = vi.hoisted(() => ({
  get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(),
}));
vi.mock('@/services/api', () => ({ default: mockApi }));

vi.mock('sonner', () => ({
  toast: { success: vi.fn(), error: vi.fn() },
}));

const mockSubscriptions = [
  {
    id: 1,
    tenant_id: 'tenant-1',
    tenant_name: 'Acme Corp',
    tenant_status: 'active',
    plan_id: 1,
    plan_name: 'Pro',
    starts_at: '2025-01-01',
    ends_at: '2026-01-01',
    status: 'active',
    created_at: '2025-01-01 00:00:00',
  },
  {
    id: 2,
    tenant_id: 'tenant-2',
    tenant_name: 'Globex Inc',
    tenant_status: 'active',
    plan_id: 2,
    plan_name: 'Enterprise',
    starts_at: '2025-03-15',
    ends_at: '2026-03-15',
    status: 'active',
    created_at: '2025-03-15 00:00:00',
  },
];

describe('Subscriptions', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders subscription rows from API data', async () => {
    mockApi.get.mockResolvedValue({ data: { subscriptions: mockSubscriptions } });

    renderWithProviders(<Subscriptions />);

    await waitFor(() => {
      expect(screen.getByText('Acme Corp')).toBeInTheDocument();
    });
    expect(screen.getByText('Globex Inc')).toBeInTheDocument();
    expect(screen.getByText('Pro')).toBeInTheDocument();
    expect(screen.getByText('Enterprise')).toBeInTheDocument();
  });

  it('renders tenant_id as caption below tenant name', async () => {
    mockApi.get.mockResolvedValue({ data: { subscriptions: mockSubscriptions } });

    renderWithProviders(<Subscriptions />);

    await waitFor(() => {
      expect(screen.getByText('tenant-1')).toBeInTheDocument();
    });
  });

  it('renders active tenant name as a clickable link with launch icon', async () => {
    mockApi.get.mockResolvedValue({ data: { subscriptions: mockSubscriptions } });

    renderWithProviders(<Subscriptions />);

    await waitFor(() => {
      expect(screen.getByText('Acme Corp')).toBeInTheDocument();
    });

    const link = screen.getByText('Acme Corp').closest('button');
    expect(link).toBeInTheDocument();
  });

  it('calls onViewTenant with tenant_id when active tenant name link is clicked', async () => {
    mockApi.get.mockResolvedValue({ data: { subscriptions: mockSubscriptions } });
    const onViewTenant = vi.fn();

    renderWithProviders(<Subscriptions onViewTenant={onViewTenant} />);

    await waitFor(() => {
      expect(screen.getByText('Acme Corp')).toBeInTheDocument();
    });

    const link = screen.getByText('Acme Corp').closest('button');
    fireEvent.click(link);

    expect(onViewTenant).toHaveBeenCalledTimes(1);
    expect(onViewTenant).toHaveBeenCalledWith('tenant-1');
  });

  it('calls onViewTenant with the correct tenant_id for each active row', async () => {
    mockApi.get.mockResolvedValue({ data: { subscriptions: mockSubscriptions } });
    const onViewTenant = vi.fn();

    renderWithProviders(<Subscriptions onViewTenant={onViewTenant} />);

    await waitFor(() => {
      expect(screen.getByText('Globex Inc')).toBeInTheDocument();
    });

    const link = screen.getByText('Globex Inc').closest('button');
    fireEvent.click(link);

    expect(onViewTenant).toHaveBeenCalledWith('tenant-2');
  });

  it('renders error alert when API call fails', async () => {
    mockApi.get.mockRejectedValue(new Error('Network error'));

    renderWithProviders(<Subscriptions />);

    await waitFor(() => {
      expect(screen.getByText('Failed to fetch subscriptions')).toBeInTheDocument();
    });
  });

  it('works without onViewTenant prop', async () => {
    mockApi.get.mockResolvedValue({ data: { subscriptions: [mockSubscriptions[0]] } });

    renderWithProviders(<Subscriptions />);

    await waitFor(() => {
      expect(screen.getByText('Acme Corp')).toBeInTheDocument();
    });

    const link = screen.getByText('Acme Corp').closest('button');
    expect(() => fireEvent.click(link)).not.toThrow();
  });

  describe('tenant status visual states', () => {
    it('renders missing tenant with gray italic text and Missing chip', async () => {
      const data = [
        { ...mockSubscriptions[0], tenant_name: 'Missing Tenant', tenant_status: 'missing' },
      ];
      mockApi.get.mockResolvedValue({ data: { subscriptions: data } });

      renderWithProviders(<Subscriptions />);

      await waitFor(() => {
        expect(screen.getByText('Missing Tenant')).toBeInTheDocument();
      });
      expect(screen.getByText('Missing')).toBeInTheDocument();
    });

    it('renders deleted tenant with strikethrough text and Deleted chip', async () => {
      const data = [
        { ...mockSubscriptions[0], tenant_name: 'Deleted Tenant', tenant_status: 'deleted' },
      ];
      mockApi.get.mockResolvedValue({ data: { subscriptions: data } });

      renderWithProviders(<Subscriptions />);

      await waitFor(() => {
        expect(screen.getByText('Deleted Tenant')).toBeInTheDocument();
      });
      expect(screen.getByText('Deleted')).toBeInTheDocument();
    });

    it('renders deleted tenant view details link', async () => {
      const data = [
        { ...mockSubscriptions[0], tenant_name: 'Deleted Tenant', tenant_status: 'deleted' },
      ];
      mockApi.get.mockResolvedValue({ data: { subscriptions: data } });
      const onViewTenant = vi.fn();

      renderWithProviders(<Subscriptions onViewTenant={onViewTenant} />);

      await waitFor(() => {
        expect(screen.getByText('View details')).toBeInTheDocument();
      });

      fireEvent.click(screen.getByText('View details'));
      expect(onViewTenant).toHaveBeenCalledWith('tenant-1');
    });

    it('does not render clickable link for missing tenant name', async () => {
      const data = [
        { ...mockSubscriptions[0], tenant_name: 'Missing Tenant', tenant_status: 'missing' },
      ];
      mockApi.get.mockResolvedValue({ data: { subscriptions: data } });
      const onViewTenant = vi.fn();

      renderWithProviders(<Subscriptions onViewTenant={onViewTenant} />);

      await waitFor(() => {
        expect(screen.getByText('Missing Tenant')).toBeInTheDocument();
      });

      const link = screen.queryByText('Missing Tenant')?.closest('button');
      expect(link).not.toBeInTheDocument();
      expect(screen.queryByText('LaunchIcon')).not.toBeInTheDocument();
    });
  });
});
