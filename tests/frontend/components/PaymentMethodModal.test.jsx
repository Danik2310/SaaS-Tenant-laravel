import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import PaymentMethodModal from '../../../../resources/js/modules/landlord/billing/components/PaymentMethodModal';

// Mock the API
jest.mock('../../../../resources/js/services/api', () => ({
    post: jest.fn(),
    put: jest.fn(),
}));

describe('PaymentMethodModal', () => {
    const mockProps = {
        open: true,
        onClose: jest.fn(),
        editingPayment: null,
        fetchPaymentMethods: jest.fn(),
        setError: jest.fn(),
    };

    beforeEach(() => {
        jest.clearAllMocks();
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
        const mockApi = require('../../../../resources/js/services/api');
        mockApi.post.mockResolvedValueOnce({});

        render(<PaymentMethodModal {...mockProps} />);

        // Fill form
        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'New Payment' } });
        fireEvent.change(screen.getByLabelText('Provider'), { target: { value: 'stripe' } });
        fireEvent.change(screen.getByLabelText('API Key'), { target: { value: 'sk_test_long_enough_key' } });
        fireEvent.change(screen.getByLabelText('Secret Key'), { target: { value: 'sk_live_long_enough_key' } });

        const saveButton = screen.getByText('Save');
        fireEvent.click(saveButton);

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
        const mockApi = require('../../../../resources/js/services/api');
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

        render(<PaymentMethodModal {...editingProps} />);

        // Modify form
        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Updated Name' } });
        fireEvent.change(screen.getByLabelText('Active'), { target: { checked: false } });

        const saveButton = screen.getByText('Save');
        fireEvent.click(saveButton);

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
        const mockApi = require('../../../../resources/js/services/api');
        mockApi.post.mockRejectedValueOnce({
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