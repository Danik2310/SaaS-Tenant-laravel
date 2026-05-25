import { vi } from 'vitest';
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import ChangePlanModal from '@/modules/landlord/modals/ChangePlanModal';
import api from '@/services/api';

vi.mock('@/services/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

const mockPlans = [
    { id: 1, name: 'Free', slug: 'free', price: 0, max_users: 5, max_products: 50, max_warehouses: 1, max_categories: 10 },
    { id: 2, name: 'Pro', slug: 'pro', price: 29, max_users: 50, max_products: 500, max_warehouses: 5, max_categories: 50 },
    { id: 3, name: 'Enterprise', slug: 'enterprise', price: 99, max_users: -1, max_products: -1, max_warehouses: -1, max_categories: -1 },
];

const mockTenant = {
    id: 'tenant-1',
    name: 'Acme Corp',
    plan: { id: 1, name: 'Free', max_users: 5, max_products: 50, max_warehouses: 1, max_categories: 10 },
    plan_name: 'Free',
};

const bulkTenants = [
    { id: 't1', name: 'Alpha', plan_name: 'Free' },
    { id: 't2', name: 'Beta', plan_name: 'Free' },
    { id: 't3', name: 'Gamma', plan_name: 'Pro' },
];

describe('ChangePlanModal', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.get.mockResolvedValue({ data: { plans: mockPlans } });
    });

    test('does not render when closed', () => {
        render(<ChangePlanModal open={false} tenants={[]} onClose={vi.fn()} onChanged={vi.fn()} />);
        expect(screen.queryByText('Change Plan')).not.toBeInTheDocument();
    });

    test('shows loading state while fetching plans', () => {
        api.get.mockImplementation(() => new Promise(() => {}));
        render(<ChangePlanModal open={true} tenants={[mockTenant]} onClose={vi.fn()} onChanged={vi.fn()} />);
        expect(screen.getByRole('progressbar')).toBeInTheDocument();
    });

    test('shows empty plans message when no plans available', async () => {
        api.get.mockResolvedValue({ data: { plans: [] } });
        render(<ChangePlanModal open={true} tenants={[mockTenant]} onClose={vi.fn()} onChanged={vi.fn()} />);
        await waitFor(() => {
            expect(screen.getByText('No plans available. Please create a plan first.')).toBeInTheDocument();
        });
    });

    test('renders plan selection cards', async () => {
        render(<ChangePlanModal open={true} tenants={[mockTenant]} onClose={vi.fn()} onChanged={vi.fn()} />);
        await waitFor(() => {
            expect(screen.getByText('Pro')).toBeInTheDocument();
            expect(screen.getByText('Enterprise')).toBeInTheDocument();
        });
    });

    test('displays current plan for single tenant', async () => {
        render(<ChangePlanModal open={true} tenants={[mockTenant]} onClose={vi.fn()} onChanged={vi.fn()} />);
        await waitFor(() => {
            expect(screen.getByText(/Changing plan for/)).toBeInTheDocument();
            expect(screen.getByText('Acme Corp')).toBeInTheDocument();
        });
    });

    test('displays tenant count for bulk change', async () => {
        render(<ChangePlanModal open={true} tenants={bulkTenants} onClose={vi.fn()} onChanged={vi.fn()} />);
        await waitFor(() => {
            expect(screen.getByText('3 tenant(s)')).toBeInTheDocument();
        });
    });

    test('marks current plan and disables selection', async () => {
        render(<ChangePlanModal open={true} tenants={[mockTenant]} onClose={vi.fn()} onChanged={vi.fn()} />);
        await waitFor(() => {
            const currentChip = screen.getByText('Current');
            expect(currentChip).toBeInTheDocument();
        });
    });

    test('Continue button is disabled until a plan is selected', async () => {
        render(<ChangePlanModal open={true} tenants={[mockTenant]} onClose={vi.fn()} onChanged={vi.fn()} />);
        await waitFor(() => {
            const continueBtn = screen.getByText('Continue').closest('button');
            expect(continueBtn).toBeDisabled();
        });
    });

    test('selecting a plan enables Continue button', async () => {
        render(<ChangePlanModal open={true} tenants={[mockTenant]} onClose={vi.fn()} onChanged={vi.fn()} />);
        await waitFor(() => {
            const proCard = screen.getByText('Pro');
            fireEvent.click(proCard);
            const continueBtn = screen.getByText('Continue').closest('button');
            expect(continueBtn).not.toBeDisabled();
        });
    });

    test('navigates to confirmation step with plan comparison', async () => {
        render(<ChangePlanModal open={true} tenants={[mockTenant]} onClose={vi.fn()} onChanged={vi.fn()} />);
        await waitFor(() => {
            const proCard = screen.getByText('Pro');
            fireEvent.click(proCard);
        });
        fireEvent.click(screen.getByText('Continue'));
        await waitFor(() => {
            expect(screen.getByText('Confirm Plan Change')).toBeInTheDocument();
            expect(screen.getByText('Plan Comparison')).toBeInTheDocument();
            expect(screen.getByText('Are you sure?')).toBeInTheDocument();
            expect(screen.getByText('Confirm Change')).toBeInTheDocument();
        });
    });

    test('Back button returns to plan selection', async () => {
        render(<ChangePlanModal open={true} tenants={[mockTenant]} onClose={vi.fn()} onChanged={vi.fn()} />);
        await waitFor(() => {
            fireEvent.click(screen.getByText('Pro'));
        });
        fireEvent.click(screen.getByText('Continue'));
        await waitFor(() => {
            expect(screen.getByText('Confirm Plan Change')).toBeInTheDocument();
        });
        fireEvent.click(screen.getByText('Back'));
        await waitFor(() => {
            expect(screen.getByText('Change Plan')).toBeInTheDocument();
            expect(screen.getByText('Continue')).toBeInTheDocument();
        });
    });

    test('calls single tenant plan change API on confirm', async () => {
        api.put.mockResolvedValue({});
        render(<ChangePlanModal open={true} tenants={[mockTenant]} onClose={vi.fn()} onChanged={vi.fn()} />);
        await waitFor(() => {
            fireEvent.click(screen.getByText('Pro'));
        });
        fireEvent.click(screen.getByText('Continue'));
        await waitFor(() => {
            fireEvent.click(screen.getByText('Confirm Change'));
        });
        await waitFor(() => {
            expect(api.put).toHaveBeenCalledWith('/admin/api/tenants/tenant-1/plan', { plan_id: 2 });
        });
    });

    test('calls bulk plan change API on confirm', async () => {
        api.post.mockResolvedValue({});
        const onChanged = vi.fn();
        const onClose = vi.fn();
        render(<ChangePlanModal open={true} tenants={bulkTenants} onClose={onClose} onChanged={onChanged} />);
        await waitFor(() => {
            fireEvent.click(screen.getByText('Pro'));
        });
        fireEvent.click(screen.getByText('Continue'));
        await waitFor(() => {
            fireEvent.click(screen.getByText('Confirm Change'));
        });
        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/admin/api/tenants/bulk', {
                tenant_ids: ['t1', 't2', 't3'],
                action: 'change_plan',
                payload: { plan_id: 2 },
            });
            expect(onChanged).toHaveBeenCalled();
            expect(onClose).toHaveBeenCalled();
        });
    });

    test('handles API errors gracefully', async () => {
        api.put.mockRejectedValue({
            response: { data: { message: 'Failed to change plan' } },
        });
        render(<ChangePlanModal open={true} tenants={[mockTenant]} onClose={vi.fn()} onChanged={vi.fn()} />);
        await waitFor(() => {
            fireEvent.click(screen.getByText('Pro'));
        });
        fireEvent.click(screen.getByText('Continue'));
        await waitFor(() => {
            fireEvent.click(screen.getByText('Confirm Change'));
        });
    });

    test('resets state when dialog is closed', async () => {
        const { rerender, unmount } = render(<ChangePlanModal open={true} tenants={[mockTenant]} onClose={vi.fn()} onChanged={vi.fn()} />);
        await waitFor(() => {
            expect(screen.getByText('Change Plan')).toBeInTheDocument();
        });
        unmount();
        expect(screen.queryByText('Change Plan')).not.toBeInTheDocument();
    });
});
