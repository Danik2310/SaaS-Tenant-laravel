import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import PaymentHistoryDialog from '@/modules/billing/components/PaymentHistoryDialog';
import { resetMoneyCache } from '@/shared/money';

const mockApi = vi.hoisted(() => ({
  get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(),
}));
vi.mock('@/services/api', () => ({ default: mockApi }));
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

const subscription = { id: 7, tenant_name: 'Acme', plan_name: 'Pro' };

const paymentsResponse = {
  data: {
    payments: [
      {
        id: 1,
        subscription_id: 7,
        amount: 29.99,
        method: 'stripe',
        reference: 'ref-1',
        status: 'completed',
        paid_at: '2026-07-01T00:00:00Z',
        notes: null,
      },
    ],
  },
};

function mockExchangeRates(displayCurrency) {
  mockApi.get.mockImplementation((url) => {
    if (String(url).includes('/admin/api/exchange-rates')) {
      return Promise.resolve({
        data: {
          base: 'USD',
          display_currency: displayCurrency,
          rates: { USD: 1, EUR: 0.92 },
          currencies: [],
          updated_at: null,
          is_live: false,
        },
      });
    }
    if (String(url).includes('/payments')) return Promise.resolve(paymentsResponse);
    return Promise.reject(new Error('Unexpected request: ' + url));
  });
}

describe('PaymentHistoryDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    resetMoneyCache();
    mockExchangeRates('USD');
  });

  test('prefills the amount in the display currency when editing', async () => {
    mockExchangeRates('EUR');
    mockApi.put.mockResolvedValue({ data: { payment: {} } });

    renderWithProviders(<PaymentHistoryDialog open subscription={subscription} onClose={() => {}} />);

    await screen.findByText('ref-1');
    fireEvent.click(screen.getByTestId('edit-payment-btn'));

    const amount = screen.getByLabelText('Amount');
    await waitFor(() => expect(amount).toHaveValue(27.59));

    fireEvent.change(amount, { target: { value: '30' } });
    fireEvent.click(screen.getByRole('button', { name: 'Update Payment' }));

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        '/admin/api/subscriptions/7/payments/1',
        expect.objectContaining({ amount: 32.61 }),
      );
    });
  });

  test('keeps the stored USD amount when saving an edit untouched', async () => {
    mockExchangeRates('EUR');
    mockApi.put.mockResolvedValue({ data: { payment: {} } });

    renderWithProviders(<PaymentHistoryDialog open subscription={subscription} onClose={() => {}} />);

    await screen.findByText('ref-1');
    fireEvent.click(screen.getByTestId('edit-payment-btn'));

    const amount = screen.getByLabelText('Amount');
    await waitFor(() => expect(amount).toHaveValue(27.59));

    fireEvent.click(screen.getByRole('button', { name: 'Update Payment' }));

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        '/admin/api/subscriptions/7/payments/1',
        expect.objectContaining({ amount: 29.99 }),
      );
    });
  });

  test('records a new payment in the display currency, converting to USD', async () => {
    mockExchangeRates('EUR');
    mockApi.post.mockResolvedValue({ data: { payment: {} } });

    renderWithProviders(<PaymentHistoryDialog open subscription={subscription} onClose={() => {}} />);

    await screen.findByText('ref-1');
    fireEvent.click(screen.getByRole('button', { name: 'Record Payment' }));

    const amount = screen.getByLabelText('Amount');
    fireEvent.change(amount, { target: { value: '46' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save Payment' }));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/admin/api/subscriptions/7/payments',
        expect.objectContaining({ amount: 50 }),
      );
    });
  });
});
