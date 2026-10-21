import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import Payments from '@/Pages/Tenant/Billing/Payments';

const { routerGetMock, usePageMock } = vi.hoisted(() => ({
  routerGetMock: vi.fn(),
  usePageMock: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
  Head: ({ title }) => <title>{title}</title>,
  Link: ({ children, ...props }) => <a {...props}>{children}</a>,
  router: {
    get: (...args) => routerGetMock(...args),
    post: vi.fn(),
    visit: vi.fn(),
  },
  usePage: () => usePageMock(),
}));

vi.stubGlobal('route', (name) => `/__route/${name}`);

const paymentsPaginator = (rows, metaOverrides = {}) => ({
  data: rows,
  links: { first: null, last: null, prev: null, next: null },
  meta: {
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: rows.length,
    path: '/billing/payments',
    ...metaOverrides,
  },
});

const samplePayment = {
  id: 1,
  subscription_id: 1,
  tenant_id: 1,
  amount: '29.99',
  currency: 'USD',
  method: 'stripe',
  reference: 'pi_test_123',
  status: 'completed',
  paid_at: '2025-06-01 10:30',
  plan_name: 'Pro',
  plan_price: '29.00',
};

describe('Payments page', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    usePageMock.mockReturnValue({ props: { auth: { user: { name: 'Test User' } } } });
  });

  it('renders the current tenant payments', async () => {
    renderWithProviders(
      <Payments payments={paymentsPaginator([samplePayment])} filters={{}} />
    );

    await waitFor(() => {
      expect(screen.getAllByText('Pro').length).toBeGreaterThan(0);
    });
    expect(screen.getAllByText('pi_test_123').length).toBeGreaterThan(0);
    expect(screen.getAllByText('$29.99').length).toBeGreaterThan(0);
    // Row action icon button (Tooltip wraps it; use testid not getByTitle).
    expect(screen.getAllByTestId('view-payment-btn').length).toBe(1);
  });

  it('renders the empty state when there are no payments', async () => {
    renderWithProviders(
      <Payments payments={paymentsPaginator([])} filters={{}} />
    );

    await waitFor(() => {
      expect(screen.getByText('No payments found.')).toBeInTheDocument();
    });
  });

  it('requests the server-filtered list when the status filter changes', async () => {
    renderWithProviders(
      <Payments payments={paymentsPaginator([samplePayment])} filters={{}} />
    );

    const select = screen.getByTestId('status-filter');
    fireEvent.change(select, { target: { value: 'pending' } });

    await waitFor(() => {
      expect(routerGetMock).toHaveBeenCalledWith(
        '/__route/billing.payments.index',
        { status: 'pending', page: 1 },
        { preserveState: true, replace: true, preserveScroll: true }
      );
    });
  });

  it('seeds the status filter from the filters prop', async () => {
    renderWithProviders(
      <Payments payments={paymentsPaginator([samplePayment])} filters={{ status: 'pending' }} />
    );

    await waitFor(() => {
      expect(screen.getByTestId('status-filter')).toBeInTheDocument();
    });
    expect(screen.getByText('Pending')).toBeInTheDocument();
  });
});
