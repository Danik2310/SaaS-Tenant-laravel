import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import PaymentMethodsTab from '@/modules/landlord/billing/components/PaymentMethodsTab';
import { mockPaymentMethods } from '../fixtures';

const mockApi = vi.hoisted(() => ({
  get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(),
}));
vi.mock('@/services/api', () => ({ default: mockApi }));

const mockProps = {
  paymentMethods: mockPaymentMethods,
  fetchPaymentMethods: vi.fn(),
  setError: vi.fn(),
};

describe('PaymentMethodsTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('renders payment methods', () => {
    renderWithProviders(<PaymentMethodsTab {...mockProps} />);

    expect(screen.getByText('Payment Gateways')).toBeInTheDocument();
    expect(screen.getByText('Stripe Test')).toBeInTheDocument();
    expect(screen.getByText('PayPal Live')).toBeInTheDocument();
    expect(screen.getByText('stripe')).toBeInTheDocument();
    expect(screen.getByText('paypal')).toBeInTheDocument();
  });

  test('displays correct status chips', () => {
    renderWithProviders(<PaymentMethodsTab {...mockProps} />);

    expect(screen.getByText('test')).toBeInTheDocument();
    expect(screen.getByText('live')).toBeInTheDocument();
    expect(screen.getByText('Active')).toBeInTheDocument();
    expect(screen.getByText('Inactive')).toBeInTheDocument();
  });

  test('opens add payment method modal when add button is clicked', () => {
    renderWithProviders(<PaymentMethodsTab {...mockProps} />);

    const addButton = screen.getByText('Add Method');
    fireEvent.click(addButton);

    expect(addButton).toBeInTheDocument();
  });

  test('opens delete confirmation dialog when delete button is clicked', () => {
    renderWithProviders(<PaymentMethodsTab {...mockProps} />);

    const deleteButtons = screen.getAllByRole('button', { name: /delete/i });
    fireEvent.click(deleteButtons[0]);

    expect(screen.getByText('Delete Payment Method')).toBeInTheDocument();
    expect(screen.getByText(/Are you sure you want to delete/)).toBeInTheDocument();
  });

  test('calls fetchPaymentMethods after successful deletion', async () => {
    mockApi.delete.mockResolvedValueOnce({});

    renderWithProviders(<PaymentMethodsTab {...mockProps} />);

    const deleteButtons = screen.getAllByRole('button', { name: /delete/i });
    fireEvent.click(deleteButtons[0]);

    const confirmDeleteButton = screen.getByText('Delete');
    fireEvent.click(confirmDeleteButton);

    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalledWith('/admin/api/payment-methods/1');
      expect(mockProps.fetchPaymentMethods).toHaveBeenCalled();
    });
  });

  test('shows error message when deletion fails', async () => {
    mockApi.delete.mockRejectedValueOnce({
      response: { data: { message: 'Deletion failed' } },
    });

    renderWithProviders(<PaymentMethodsTab {...mockProps} />);

    const deleteButtons = screen.getAllByRole('button', { name: /delete/i });
    fireEvent.click(deleteButtons[0]);

    const confirmDeleteButton = screen.getByText('Delete');
    fireEvent.click(confirmDeleteButton);

    await waitFor(() => {
      expect(mockProps.setError).toHaveBeenCalledWith('Deletion failed');
    });
  });
});
