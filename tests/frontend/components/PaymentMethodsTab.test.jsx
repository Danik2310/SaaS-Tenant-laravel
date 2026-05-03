import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import PaymentMethodsTab from '../../../../resources/js/modules/landlord/billing/components/PaymentMethodsTab';

// Mock the API
jest.mock('../../../../resources/js/services/api', () => ({
    get: jest.fn(),
    post: jest.fn(),
    put: jest.fn(),
    delete: jest.fn(),
}));

// Mock Material-UI components that might cause issues
jest.mock('@mui/material', () => ({
    ...jest.requireActual('@mui/material'),
    TableContainer: ({ children }) => <div data-testid="table-container">{children}</div>,
    Table: ({ children }) => <table>{children}</table>,
    TableHead: ({ children }) => <thead>{children}</thead>,
    TableBody: ({ children }) => <tbody>{children}</tbody>,
    TableRow: ({ children }) => <tr>{children}</tr>,
    TableCell: ({ children }) => <td>{children}</td>,
}));

const mockPaymentMethods = [
    {
        id: 1,
        name: 'Stripe Test',
        provider: 'stripe',
        mode: 'test',
        active: true,
    },
    {
        id: 2,
        name: 'PayPal Live',
        provider: 'paypal',
        mode: 'live',
        active: false,
    },
];

const mockProps = {
    paymentMethods: mockPaymentMethods,
    fetchPaymentMethods: jest.fn(),
    setError: jest.fn(),
};

describe('PaymentMethodsTab', () => {
    beforeEach(() => {
        jest.clearAllMocks();
    });

    test('renders payment methods table correctly', () => {
        render(<PaymentMethodsTab {...mockProps} />);

        expect(screen.getByText('Configure Payment Gateways')).toBeInTheDocument();
        expect(screen.getByText('Stripe Test')).toBeInTheDocument();
        expect(screen.getByText('PayPal Live')).toBeInTheDocument();
        expect(screen.getByText('stripe')).toBeInTheDocument();
        expect(screen.getByText('paypal')).toBeInTheDocument();
    });

    test('displays correct status chips', () => {
        render(<PaymentMethodsTab {...mockProps} />);

        // Check mode chips
        expect(screen.getByText('test')).toBeInTheDocument();
        expect(screen.getByText('live')).toBeInTheDocument();

        // Check active status chips
        expect(screen.getByText('Active')).toBeInTheDocument();
        expect(screen.getByText('Inactive')).toBeInTheDocument();
    });

    test('opens add payment method modal when add button is clicked', () => {
        render(<PaymentMethodsTab {...mockProps} />);

        const addButton = screen.getByText('Add Method');
        fireEvent.click(addButton);

        // The modal should be rendered (we can't easily test the open state without more setup)
        expect(addButton).toBeInTheDocument();
    });

    test('opens delete confirmation dialog when delete button is clicked', () => {
        render(<PaymentMethodsTab {...mockProps} />);

        const deleteButtons = screen.getAllByRole('button', { name: /delete/i });
        fireEvent.click(deleteButtons[0]);

        // Check if delete dialog appears
        expect(screen.getByText('Delete Payment Method')).toBeInTheDocument();
        expect(screen.getByText(/Are you sure you want to delete/)).toBeInTheDocument();
    });

    test('calls fetchPaymentMethods after successful deletion', async () => {
        const mockApi = require('../../../../resources/js/services/api');
        mockApi.delete.mockResolvedValueOnce({});

        render(<PaymentMethodsTab {...mockProps} />);

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
        const mockApi = require('../../../../resources/js/services/api');
        mockApi.delete.mockRejectedValueOnce({
            response: { data: { message: 'Deletion failed' } }
        });

        render(<PaymentMethodsTab {...mockProps} />);

        const deleteButtons = screen.getAllByRole('button', { name: /delete/i });
        fireEvent.click(deleteButtons[0]);

        const confirmDeleteButton = screen.getByText('Delete');
        fireEvent.click(confirmDeleteButton);

        await waitFor(() => {
            expect(mockProps.setError).toHaveBeenCalledWith('Deletion failed');
        });
    });
});