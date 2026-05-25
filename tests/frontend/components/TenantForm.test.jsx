import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import TenantForm from '@/modules/landlord/tenants/TenantForm';
import { mockPlans } from '../fixtures';

const mockApi = vi.hoisted(() => ({
  get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(),
}));
vi.mock('@/services/api', () => ({ default: mockApi }));

vi.mock('sonner', () => ({
  toast: { success: vi.fn(), error: vi.fn() },
}));

const mockOnSubmit = vi.fn();
const mockOnCancel = vi.fn();

function renderCreate() {
  return renderWithProviders(
    <TenantForm tenant={null} onSubmit={mockOnSubmit} onCancel={mockOnCancel} />
  );
}

function renderEdit() {
  return renderWithProviders(
    <TenantForm
      tenant={{ id: 't-1', name: 'Acme Corp', email: 'admin@acme.com', domain: 'acme.localhost', plan: { id: 2 } }}
      onSubmit={mockOnSubmit}
      onCancel={mockOnCancel}
    />
  );
}

describe('TenantForm', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockResolvedValue({ data: { plans: mockPlans } });
  });

  test('renders create form with all fields', async () => {
    renderCreate();

    expect(screen.getByText('Create New Tenant')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('e.g., Acme Corp')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('admin@tenant.com')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('acme.localhost')).toBeInTheDocument();

    await waitFor(() => {
      expect(screen.getByText('Pro ($29/mo)')).toBeInTheDocument();
    });
  });

  test('renders edit form with pre-filled data', async () => {
    renderEdit();

    expect(screen.getByText('Edit Tenant')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Acme Corp')).toBeInTheDocument();
    expect(screen.getByDisplayValue('admin@acme.com')).toBeInTheDocument();
    expect(screen.getByText(/acme\.localhost/)).toBeInTheDocument();
  });

  test('edit form does not show domain input', () => {
    renderEdit();

    expect(screen.queryByPlaceholderText('acme.localhost')).not.toBeInTheDocument();
  });

  test('fetches plans on mount', async () => {
    renderCreate();

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith('/admin/api/plans-list');
    });
  });

  test('calls onSubmit with correct payload for create', async () => {
    mockOnSubmit.mockResolvedValue();

    renderCreate();

    await waitFor(() => {
      expect(screen.getByText('Pro ($29/mo)')).toBeInTheDocument();
    });

    fireEvent.change(screen.getByPlaceholderText('e.g., Acme Corp'), { target: { value: 'NewCo' } });
    fireEvent.change(screen.getByPlaceholderText('admin@tenant.com'), { target: { value: 'admin@newco.com' } });
    fireEvent.change(screen.getByPlaceholderText('acme.localhost'), { target: { value: 'newco.localhost' } });
    fireEvent.change(screen.getByRole('combobox'), { target: { value: '2' } });

    fireEvent.click(screen.getByText('Create Tenant'));

    await waitFor(() => {
      expect(mockOnSubmit).toHaveBeenCalledWith({
        name: 'NewCo',
        email: 'admin@newco.com',
        domain: 'newco.localhost',
        plan: 'pro',
      });
    });
  });

  test('calls onSubmit with correct payload for edit', async () => {
    mockOnSubmit.mockResolvedValue();

    renderEdit();

    await waitFor(() => {
      expect(screen.getByDisplayValue('Acme Corp')).toBeInTheDocument();
    });

    fireEvent.change(screen.getByDisplayValue('Acme Corp'), { target: { value: 'Updated Corp' } });
    fireEvent.click(screen.getByText('Update Tenant'));

    await waitFor(() => {
      expect(mockOnSubmit).toHaveBeenCalledWith({
        id: 't-1',
        name: 'Updated Corp',
        email: 'admin@acme.com',
        plan_id: 2,
      });
    });
  });

  test('shows error message on submit failure', async () => {
    mockOnSubmit.mockRejectedValue(new Error('Server error'));

    renderCreate();

    await waitFor(() => {
      expect(screen.getByPlaceholderText('e.g., Acme Corp')).toBeInTheDocument();
    });

    fireEvent.change(screen.getByPlaceholderText('e.g., Acme Corp'), { target: { value: 'Test' } });
    fireEvent.change(screen.getByPlaceholderText('admin@tenant.com'), { target: { value: 'a@b.com' } });
    fireEvent.change(screen.getByPlaceholderText('acme.localhost'), { target: { value: 't.localhost' } });

    fireEvent.click(screen.getByText('Create Tenant'));

    await waitFor(() => {
      expect(screen.getByText('Server error')).toBeInTheDocument();
    });
  });

  test('cancel button calls onCancel', () => {
    renderCreate();

    fireEvent.click(screen.getByText('Cancel'));
    expect(mockOnCancel).toHaveBeenCalled();
  });
});
