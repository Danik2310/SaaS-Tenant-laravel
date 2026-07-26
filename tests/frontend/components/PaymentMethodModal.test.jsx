import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import PaymentMethodModal from '@/modules/billing/components/PaymentMethodModal';
import { mockPaymentMethod } from '../fixtures';

const mockApi = vi.hoisted(() => ({
  get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(),
}));
vi.mock('@/services/api', () => ({ default: mockApi }));

describe('PaymentMethodModal', () => {
  const mockProps = {
    open: true,
    onClose: vi.fn(),
    editingPayment: null,
    fetchPaymentMethods: vi.fn(),
    setError: vi.fn(),
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('renders add payment method modal correctly', () => {
    renderWithProviders(<PaymentMethodModal {...mockProps} />);

    expect(screen.getByText('Add Payment Method')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('e.g., Stripe Production')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('pk_test_...')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('sk_test_...')).toBeInTheDocument();
    expect(screen.getByText('Test (Sandbox)')).toBeInTheDocument();
    expect(screen.getByText('Active - Enable this payment method for tenants')).toBeInTheDocument();
  });

  test('renders edit payment method modal with pre-filled data', () => {
    const editingProps = { ...mockProps, editingPayment: mockPaymentMethod };

    renderWithProviders(<PaymentMethodModal {...editingProps} />);

    expect(screen.getByText('Edit Payment Method')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Test Payment')).toBeInTheDocument();
    expect(screen.getByDisplayValue('sk_test_123')).toBeInTheDocument();
    expect(screen.getByDisplayValue('sk_live_456')).toBeInTheDocument();
  });

  test('validates required fields', async () => {
    renderWithProviders(<PaymentMethodModal {...mockProps} />);

    fireEvent.click(screen.getByText('Create'));

    await waitFor(() => {
      expect(mockProps.setError).toHaveBeenCalledWith('Please fix the validation errors below');
    });
  });

  test('validates API key length', async () => {
    renderWithProviders(<PaymentMethodModal {...mockProps} />);

    fireEvent.change(screen.getByPlaceholderText('e.g., Stripe Production'), { target: { value: 'Test' } });
    fireEvent.change(screen.getByPlaceholderText('pk_test_...'), { target: { value: 'short' } });
    fireEvent.change(screen.getByPlaceholderText('sk_test_...'), { target: { value: 'sk_live_long_enough_key' } });

    fireEvent.click(screen.getByText('Create'));

    await waitFor(() => {
      expect(mockProps.setError).toHaveBeenCalledWith('Please fix the validation errors below');
    });
  });

  test('validates secret key length', async () => {
    renderWithProviders(<PaymentMethodModal {...mockProps} />);

    fireEvent.change(screen.getByPlaceholderText('e.g., Stripe Production'), { target: { value: 'Test' } });
    fireEvent.change(screen.getByPlaceholderText('pk_test_...'), { target: { value: 'sk_test_long_enough_key' } });
    fireEvent.change(screen.getByPlaceholderText('sk_test_...'), { target: { value: 'short' } });

    fireEvent.click(screen.getByText('Create'));

    await waitFor(() => {
      expect(mockProps.setError).toHaveBeenCalledWith('Please fix the validation errors below');
    });
  });

  test('successfully creates new payment method', async () => {
    mockApi.post.mockResolvedValueOnce({});

    renderWithProviders(<PaymentMethodModal {...mockProps} />);

    fireEvent.change(screen.getByPlaceholderText('e.g., Stripe Production'), { target: { value: 'New Payment' } });
    fireEvent.change(screen.getAllByRole('combobox')[0], { target: { value: 'stripe' } });
    fireEvent.change(screen.getByPlaceholderText('pk_test_...'), { target: { value: 'sk_test_long_enough_key' } });
    fireEvent.change(screen.getByPlaceholderText('sk_test_...'), { target: { value: 'sk_live_long_enough_key' } });

    fireEvent.click(screen.getByText('Create'));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith('/admin/api/payment-methods', {
        name: 'New Payment',
        provider: 'stripe',
        api_key: 'sk_test_long_enough_key',
        secret_key: 'sk_live_long_enough_key',
        mode: 'test',
        active: true,
      });
      expect(mockProps.onClose).toHaveBeenCalled();
      expect(mockProps.fetchPaymentMethods).toHaveBeenCalled();
    });
  });

  test('successfully updates existing payment method', async () => {
    mockApi.put.mockResolvedValueOnce({});

    const editingProps = {
      ...mockProps,
      editingPayment: {
        id: 1,
        name: 'Old Name',
        provider: 'stripe',
        api_key: 'sk_test_old',
        secret_key: 'sk_live_old',
        mode: 'test',
        active: true,
      },
    };

    renderWithProviders(<PaymentMethodModal {...editingProps} />);

    await waitFor(() => {
      expect(screen.getByDisplayValue('Old Name')).toBeInTheDocument();
    });

    fireEvent.change(screen.getByDisplayValue('Old Name'), { target: { value: 'Updated Name' } });

    const checkbox = screen.getByLabelText('Active - Enable this payment method for tenants');
    fireEvent.click(checkbox);

    fireEvent.click(screen.getByText('Update'));

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith('/admin/api/payment-methods/1', {
        name: 'Updated Name',
        provider: 'stripe',
        api_key: 'sk_test_old',
        secret_key: 'sk_live_old',
        mode: 'test',
        active: false,
      });
    });
  });

  test('handles API errors gracefully', async () => {
    mockApi.post.mockRejectedValueOnce({
      response: { data: { message: 'API Error' } },
    });

    renderWithProviders(<PaymentMethodModal {...mockProps} />);

    fireEvent.change(screen.getByPlaceholderText('e.g., Stripe Production'), { target: { value: 'Test' } });
    fireEvent.change(screen.getAllByRole('combobox')[0], { target: { value: 'stripe' } });
    fireEvent.change(screen.getByPlaceholderText('pk_test_...'), { target: { value: 'sk_test_long_enough_key' } });
    fireEvent.change(screen.getByPlaceholderText('sk_test_...'), { target: { value: 'sk_live_long_enough_key' } });

    fireEvent.click(screen.getByText('Create'));

    await waitFor(() => {
      expect(mockProps.setError).toHaveBeenCalledWith('API Error');
    });
  });
});
