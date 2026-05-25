import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import PlanModal from '@/modules/landlord/billing/components/PlanModal';

const mockApi = vi.hoisted(() => ({
  get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(),
}));
vi.mock('@/services/api', () => ({ default: mockApi }));

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

describe('PlanModal', () => {
  const baseProps = {
    open: true,
    onClose: vi.fn(),
    editingPlan: null,
    fetchPlans: vi.fn(),
    setError: vi.fn(),
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('renders add mode title', () => {
    renderWithProviders(<PlanModal {...baseProps} />);

    expect(screen.getByText('Add Plan')).toBeInTheDocument();
  });

  test('renders edit mode title', () => {
    renderWithProviders(
      <PlanModal {...baseProps} editingPlan={{ id: 1, name: 'Pro', price: 29, features: ['advanced'] }} />
    );

    expect(screen.getByText('Edit Plan')).toBeInTheDocument();
  });

  test('pre-fills form in edit mode', () => {
    renderWithProviders(
      <PlanModal {...baseProps} editingPlan={{ id: 1, name: 'Pro', price: '29', features: ['advanced', 'api'] }} />
    );

    expect(screen.getByDisplayValue('Pro')).toBeInTheDocument();
    expect(screen.getByDisplayValue('29')).toBeInTheDocument();
    expect(screen.getByDisplayValue('advanced, api')).toBeInTheDocument();
  });

  test('validates required fields', async () => {
    renderWithProviders(<PlanModal {...baseProps} />);

    fireEvent.click(screen.getByText('Create'));

    await waitFor(() => {
      expect(baseProps.setError).toHaveBeenCalledWith('Name and price are required');
    });
  });

  test('validates negative price', async () => {
    renderWithProviders(<PlanModal {...baseProps} />);

    fireEvent.change(screen.getByPlaceholderText('e.g., Pro Plan'), { target: { value: 'Test' } });
    fireEvent.change(screen.getByPlaceholderText('29.99'), { target: { value: '-10' } });

    fireEvent.click(screen.getByText('Create'));

    await waitFor(() => {
      expect(baseProps.setError).toHaveBeenCalledWith('Price must be positive');
    });
  });

  test('calls POST in create mode', async () => {
    mockApi.post.mockResolvedValue({});

    renderWithProviders(<PlanModal {...baseProps} />);

    fireEvent.change(screen.getByPlaceholderText('e.g., Pro Plan'), { target: { value: 'New Plan' } });
    fireEvent.change(screen.getByPlaceholderText('29.99'), { target: { value: '49' } });

    fireEvent.click(screen.getByText('Create'));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith('/admin/api/plans', {
        name: 'New Plan',
        price: '49',
        features: '',
      });
      expect(baseProps.onClose).toHaveBeenCalled();
      expect(baseProps.fetchPlans).toHaveBeenCalled();
    });
  });

  test('calls PUT in edit mode', async () => {
    mockApi.put.mockResolvedValue({});

    renderWithProviders(
      <PlanModal {...baseProps} editingPlan={{ id: 5, name: 'Old', price: '10', features: [] }} />
    );

    await waitFor(() => {
      expect(screen.getByDisplayValue('Old')).toBeInTheDocument();
    });

    fireEvent.change(screen.getByDisplayValue('Old'), { target: { value: 'Updated' } });
    fireEvent.click(screen.getByText('Update'));

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith('/admin/api/plans/5', {
        name: 'Updated',
        price: '10',
        features: '',
      });
    });
  });

  test('shows saving state while loading', async () => {
    mockApi.post.mockImplementation(() => new Promise(() => {}));

    renderWithProviders(<PlanModal {...baseProps} />);

    fireEvent.change(screen.getByPlaceholderText('e.g., Pro Plan'), { target: { value: 'Test' } });
    fireEvent.change(screen.getByPlaceholderText('29.99'), { target: { value: '10' } });

    fireEvent.click(screen.getByText('Create'));

    expect(screen.getByText('Saving...')).toBeInTheDocument();
  });

  test('handles API errors', async () => {
    mockApi.post.mockRejectedValue(new Error('Server error'));

    renderWithProviders(<PlanModal {...baseProps} />);

    fireEvent.change(screen.getByPlaceholderText('e.g., Pro Plan'), { target: { value: 'Test' } });
    fireEvent.change(screen.getByPlaceholderText('29.99'), { target: { value: '10' } });

    fireEvent.click(screen.getByText('Create'));

    await waitFor(() => {
      expect(baseProps.setError).toHaveBeenCalledWith('Failed to save plan. Please check your permissions.');
    });
  });

  test('cancel button fires onClose', () => {
    renderWithProviders(<PlanModal {...baseProps} />);

    fireEvent.click(screen.getByText('Cancel'));
    expect(baseProps.onClose).toHaveBeenCalled();
  });
});
