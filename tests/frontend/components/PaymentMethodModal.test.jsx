import { vi } from 'vitest';
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import * as api from '@/services/api';
import PaymentMethodModal from '@/modules/landlord/billing/components/PaymentMethodModal';

vi.mock('@/services/api');

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
        render(<PaymentMethodModal {...mockProps} />);

        expect(screen.getByText('Add Payment Method')).toBeInTheDocument();
        expect(screen.getByLabelText('Name')).toBeInTheDocument();
        expect(screen.getByLabelText('Provider')).toBeInTheDocument();
        expect(screen.getByLabelText('API Key')).toBeInTheDocument();
        expect(screen.getByLabelText('Secret Key')).toBeInTheDocument();
        expect(screen.getByLabelText('Mode')).toBeInTheDocument();
        expect(screen.getByLabelText('Active')).toBeInTheDocument();
    });

    test('renders edit payment method modal with pre-filled data', () => {
        const editingProps = {
            ...mockProps,
            editingPayment: {
                id: 1,
                name: 'Test Payment',
                provider: 'stripe',
                api_key: 'sk_test_123',
                secret_key: 'sk_live_456',
                mode: 'live',
                active: false,
            },
        };

        render(<PaymentMethodModal {...editingProps} />);

        expect(screen.getByText('Edit Payment Method')).toBeInTheDocument();
        expect(screen.getByDisplayValue('Test Payment')).toBeInTheDocument();
        expect(screen.getByDisplayValue('stripe')).toBeInTheDocument();
        expect(screen.getByDisplayValue('sk_test_123')).toBeInTheDocument();
        expect(screen.getByDisplayValue('sk_live_456')).toBeInTheDocument();
        expect(screen.getByDisplayValue('live')).toBeInTheDocument();
    });

    test('validates required fields', async () => {
        render(<PaymentMethodModal {...mockProps} />);

        const saveButton = screen.getByText('Save');
        fireEvent.click(saveButton);

        await waitFor(() => {
            expect(mockProps.setError).toHaveBeenCalledWith('All fields are required');
        });
    });

    test('validates API key length', async () => {
        render(<PaymentMethodModal {...mockProps} />);

        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Test' } });
        fireEvent.change(screen.getByLabelText('Provider'), { target: { value: 'stripe' } });
        fireEvent.change(screen.getByLabelText('API Key'), { target: { value: 'short' } });
        fireEvent.change(screen.getByLabelText('Secret Key'), { target: { value: 'sk_live_long_enough_key' } });

        const saveButton = screen.getByText('Save');
        fireEvent.click(saveButton);

        await waitFor(() => {
            expect(mockProps.setError).toHaveBeenCalledWith('API Key must be at least 10 characters');
        });
    });

    test('validates secret key length', async () => {
        render(<PaymentMethodModal {...mockProps} />);

        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Test' } });
        fireEvent.change(screen.getByLabelText('Provider'), { target: { value: 'stripe' } });
        fireEvent.change(screen.getByLabelText('API Key'), { target: { value: 'sk_test_long_enough_key' } });
        fireEvent.change(screen.getByLabelText('Secret Key'), { target: { value: 'short' } });

        const saveButton = screen.getByText('Save');
        fireEvent.click(saveButton);

        await waitFor(() => {
            expect(mockProps.setError).toHaveBeenCalledWith('Secret Key must be at least 10 characters');
        });
    });

    test('successfully creates new payment method', async () => {
        api.post.mockResolvedValueOnce({});

        render(<PaymentMethodModal {...mockProps} />);

        // Fill form
        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'New Payment' } });
        fireEvent.change(screen.getByLabelText('Provider'), { target: { value: 'stripe' } });
        fireEvent.change(screen.getByLabelText('API Key'), { target: { value: 'sk_test_long_enough_key' } });
        fireEvent.change(screen.getByLabelText('Secret Key'), { target: { value: 'sk_live_long_enough_key' } });

        const saveButton = screen.getByText('Save');
        fireEvent.click(saveButton);

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/admin/api/payment-methods', {
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
        api.put.mockResolvedValueOnce({});

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

        render(<PaymentMethodModal {...editingProps} />);

        // Modify form
        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Updated Name' } });
        fireEvent.change(screen.getByLabelText('Active'), { target: { checked: false } });

        const saveButton = screen.getByText('Save');
        fireEvent.click(saveButton);

        await waitFor(() => {
            expect(api.put).toHaveBeenCalledWith('/admin/api/payment-methods/1', {
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
        api.post.mockRejectedValueOnce({
            response: { data: { message: 'API Error' } }
        });

        render(<PaymentMethodModal {...mockProps} />);

        // Fill form
        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Test' } });
        fireEvent.change(screen.getByLabelText('Provider'), { target: { value: 'stripe' } });
        fireEvent.change(screen.getByLabelText('API Key'), { target: { value: 'sk_test_long_enough_key' } });
        fireEvent.change(screen.getByLabelText('Secret Key'), { target: { value: 'sk_live_long_enough_key' } });

        const saveButton = screen.getByText('Save');
        fireEvent.click(saveButton);

        await waitFor(() => {
            expect(mockProps.setError).toHaveBeenCalledWith('API Error');
        });
    });
});