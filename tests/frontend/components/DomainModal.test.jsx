import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import DomainModal from '@/modules/shared/modals/DomainModal';
import api from '@/services/api';
import { toast } from 'sonner';

const { useAuthContextMock } = vi.hoisted(() => ({ useAuthContextMock: vi.fn() }));
vi.mock('@/context/AuthContext', () => ({ useAuthContext: () => useAuthContextMock() }));

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
vi.mock('@/services/api', () => ({ default: { post: vi.fn(), delete: vi.fn() } }));

const activeTenant = {
  id: 't-1',
  name: 'Acme Corp',
  email: 'admin@acme.com',
  status: 'Active',
  plan_name: 'Pro',
  plan_slug: 'pro',
  created_at: '2025-01-15T00:00:00Z',
  is_on_trial: false,
  trial_has_expired: false,
  all_domains: [
    { id: 1, domain: 'acme.sasapp', is_primary: true },
    { id: 2, domain: 'acme-backup.sasapp', is_primary: false },
  ],
};

const suspendedTenant = {
  ...activeTenant,
  status: 'Suspended',
  plan_name: 'Free',
  plan_slug: 'free',
};

const trialTenant = {
  ...activeTenant,
  is_on_trial: true,
  trial_has_expired: false,
  trial_ends_at: '2025-06-01T00:00:00Z',
};

const expiredTrialTenant = {
  ...activeTenant,
  is_on_trial: true,
  trial_has_expired: true,
  trial_ends_at: '2024-12-01T00:00:00Z',
};

const deletedTenant = {
  ...activeTenant,
  status: 'Deleted',
  is_deleted: true,
};

describe('DomainModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    useAuthContextMock.mockReturnValue({
      permissions: ['view tenants', 'impersonate tenants', 'edit tenants', 'restore tenants'],
    });
    Object.assign(navigator, {
      clipboard: { writeText: vi.fn().mockResolvedValue() },
    });
  });

  test('returns null when tenant is null', () => {
    const { container } = renderWithProviders(
      <DomainModal tenant={null} onClose={vi.fn()} />
    );
    expect(container.innerHTML).toBe('');
  });

  test('renders tenant name and ID', () => {
    renderWithProviders(
      <DomainModal tenant={activeTenant} onClose={vi.fn()} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} />
    );

    expect(screen.getByText('Acme Corp')).toBeInTheDocument();
    expect(screen.getByText(/ID: t-1/)).toBeInTheDocument();
  });

  test('shows Active chip for active tenant', () => {
    renderWithProviders(
      <DomainModal tenant={activeTenant} onClose={vi.fn()} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} />
    );

    expect(screen.getByText('Active')).toBeInTheDocument();
  });

  test('shows Suspended chip for suspended tenant', () => {
    renderWithProviders(
      <DomainModal tenant={suspendedTenant} onClose={vi.fn()} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} />
    );

    expect(screen.getByText('Suspended')).toBeInTheDocument();
  });

  test('displays tenant email', () => {
    renderWithProviders(
      <DomainModal tenant={activeTenant} onClose={vi.fn()} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} />
    );

    expect(screen.getByText('admin@acme.com')).toBeInTheDocument();
  });

  test('displays plan name and slug chip', () => {
    renderWithProviders(
      <DomainModal tenant={activeTenant} onClose={vi.fn()} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} />
    );

    expect(screen.getByText(/Pro/)).toBeInTheDocument();
    expect(screen.getByText('pro')).toBeInTheDocument();
  });

  test('displays personal and business information', () => {
    const detailTenant = {
      ...activeTenant,
      company_name: 'Acme Corp LLC',
      first_name: 'Jane',
      last_name: 'Doe',
      phone: '+1 555 123 4567',
      address_line1: '123 Main St',
      address_line2: 'Suite 400',
      city: 'Springfield',
      state: 'IL',
      postal_code: '62701',
      country: 'United States',
    };
    renderWithProviders(
      <DomainModal tenant={detailTenant} onClose={vi.fn()} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} />
    );

    expect(screen.getByText(/Company:/)).toHaveTextContent('Acme Corp LLC');
    expect(screen.getByText(/Contact:/)).toHaveTextContent('Jane Doe');
    expect(screen.getByText(/Phone:/)).toHaveTextContent('+1 555 123 4567');
    expect(screen.getByText(/Address:/)).toHaveTextContent('123 Main St, Suite 400, Springfield, IL, 62701, United States');
  });

  test('shows placeholders when business details are missing', () => {
    renderWithProviders(
      <DomainModal tenant={activeTenant} onClose={vi.fn()} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} />
    );

    expect(screen.getByText(/Company:/)).toHaveTextContent('—');
    expect(screen.getByText(/Contact:/)).toHaveTextContent('—');
    expect(screen.getByText(/Phone:/)).toHaveTextContent('—');
    expect(screen.getByText(/Address:/)).toHaveTextContent('—');
  });

  test('shows trial active section', () => {
    renderWithProviders(
      <DomainModal tenant={trialTenant} onClose={vi.fn()} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} />
    );

    expect(screen.getByText(/Trial active/)).toBeInTheDocument();
  });

  test('shows trial expired section', () => {
    renderWithProviders(
      <DomainModal tenant={expiredTrialTenant} onClose={vi.fn()} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} />
    );

    expect(screen.getByText(/Trial expired/)).toBeInTheDocument();
  });

  test('lists all domains', () => {
    renderWithProviders(
      <DomainModal tenant={activeTenant} onClose={vi.fn()} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} />
    );

    expect(screen.getByText('acme.sasapp')).toBeInTheDocument();
    expect(screen.getByText('acme-backup.sasapp')).toBeInTheDocument();
  });

  test('shows empty state when no domains', () => {
    const noDomains = { ...activeTenant, all_domains: [] };
    renderWithProviders(
      <DomainModal tenant={noDomains} onClose={vi.fn()} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} />
    );

    expect(screen.getByText('No domains configured')).toBeInTheDocument();
  });

  test('copies domain to clipboard on copy click', async () => {
    renderWithProviders(
      <DomainModal tenant={activeTenant} onClose={vi.fn()} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} />
    );

    const copyButtons = screen.getAllByRole('button', { name: /copy/i });
    fireEvent.click(copyButtons[0]);

    await waitFor(() => {
      expect(navigator.clipboard.writeText).toHaveBeenCalledWith('acme.sasapp');
    });
  });

  test('shows Quick Action cards and fires callbacks', () => {
    const onImpersonate = vi.fn();
    const onViewDatabase = vi.fn();
    const onRunMigrations = vi.fn();

    renderWithProviders(
      <DomainModal
        tenant={activeTenant}
        onClose={vi.fn()}
        onImpersonate={onImpersonate}
        onViewDatabase={onViewDatabase}
        onRunMigrations={onRunMigrations}
        onRestore={vi.fn()}
      />
    );

    expect(screen.getByText('Impersonate')).toBeInTheDocument();
    expect(screen.getByText('Database Info')).toBeInTheDocument();
    expect(screen.getByText('Run Migrations')).toBeInTheDocument();

    fireEvent.click(screen.getByText('Impersonate'));
    expect(onImpersonate).toHaveBeenCalledWith(activeTenant);

    fireEvent.click(screen.getByText('Database Info'));
    expect(onViewDatabase).toHaveBeenCalledWith(activeTenant);

    fireEvent.click(screen.getByText('Run Migrations'));
    expect(onRunMigrations).toHaveBeenCalledWith(activeTenant);
  });

  test('hides Impersonate and Run Migrations for read-only users', () => {
    useAuthContextMock.mockReturnValue({ permissions: ['view tenants'] });

    renderWithProviders(
      <DomainModal tenant={activeTenant} onClose={vi.fn()} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} onRestore={vi.fn()} />
    );

    expect(screen.queryByText('Impersonate')).not.toBeInTheDocument();
    expect(screen.queryByText('Run Migrations')).not.toBeInTheDocument();
    expect(screen.getByText('Database Info')).toBeInTheDocument();
    expect(screen.queryByText("You don't have permission to perform actions on this tenant.")).not.toBeInTheDocument();
  });

  test('hides Restore Tenant for users without restore permission', () => {
    useAuthContextMock.mockReturnValue({ permissions: ['view tenants'] });

    renderWithProviders(
      <DomainModal tenant={deletedTenant} onClose={vi.fn()} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} onRestore={vi.fn()} />
    );

    expect(screen.queryByText('Restore Tenant')).not.toBeInTheDocument();
    expect(screen.getByText("You don't have permission to perform actions on this tenant.")).toBeInTheDocument();
  });

  test('shows Restore Tenant for users with restore permission', () => {
    useAuthContextMock.mockReturnValue({ permissions: ['view tenants', 'restore tenants'] });

    renderWithProviders(
      <DomainModal tenant={deletedTenant} onClose={vi.fn()} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} onRestore={vi.fn()} />
    );

    expect(screen.getByText('Restore Tenant')).toBeInTheDocument();
    expect(screen.queryByText("You don't have permission to perform actions on this tenant.")).not.toBeInTheDocument();
  });

  test('shows no-access message when user lacks all actions', () => {
    useAuthContextMock.mockReturnValue({
      permissions: [],
    });

    renderWithProviders(
      <DomainModal tenant={activeTenant} onClose={vi.fn()} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} onRestore={vi.fn()} />
    );

    expect(screen.queryByText('Database Info')).not.toBeInTheDocument();
    expect(screen.queryByText('Impersonate')).not.toBeInTheDocument();
    expect(screen.queryByText('Run Migrations')).not.toBeInTheDocument();
    expect(screen.getByText("You don't have permission to perform actions on this tenant.")).toBeInTheDocument();
  });

  test('close button fires onClose', () => {
    const onClose = vi.fn();

    renderWithProviders(
      <DomainModal tenant={activeTenant} onClose={onClose} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} onRestore={vi.fn()} />
    );

    fireEvent.click(screen.getByText('Close'));
    expect(onClose).toHaveBeenCalled();
  });

  test('shows Deleted chip for deleted tenant', () => {
    renderWithProviders(
      <DomainModal tenant={deletedTenant} onClose={vi.fn()} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} onRestore={vi.fn()} />
    );

    expect(screen.getByText('Deleted')).toBeInTheDocument();
    expect(screen.queryByText('Active')).not.toBeInTheDocument();
    expect(screen.queryByText('Suspended')).not.toBeInTheDocument();
  });

  test('shows Restore Tenant action for deleted tenant', () => {
    renderWithProviders(
      <DomainModal tenant={deletedTenant} onClose={vi.fn()} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} onRestore={vi.fn()} />
    );

    expect(screen.getByText('Restore Tenant')).toBeInTheDocument();
    expect(screen.getByText('Reactivate this tenant')).toBeInTheDocument();
    expect(screen.queryByText('Impersonate')).not.toBeInTheDocument();
    expect(screen.queryByText('Database Info')).not.toBeInTheDocument();
    expect(screen.queryByText('Run Migrations')).not.toBeInTheDocument();
  });

  test('clicking Restore fires onRestore with tenant id', () => {
    const onRestore = vi.fn();

    renderWithProviders(
      <DomainModal tenant={deletedTenant} onClose={vi.fn()} onImpersonate={vi.fn()} onViewDatabase={vi.fn()} onRunMigrations={vi.fn()} onRestore={onRestore} />
    );

    fireEvent.click(screen.getByText('Restore Tenant'));
    expect(onRestore).toHaveBeenCalledWith(deletedTenant.id);
  });

  test('adds a new domain on submit', async () => {
    const onTenantUpdated = vi.fn();
    const updatedTenant = {
      ...activeTenant,
      all_domains: [...activeTenant.all_domains, { id: 3, domain: 'new.sasapp', is_primary: false }],
    };
    api.post.mockResolvedValue({ data: { tenant: updatedTenant } });

    renderWithProviders(
      <DomainModal tenant={activeTenant} onClose={vi.fn()} onTenantUpdated={onTenantUpdated} />
    );

    fireEvent.change(screen.getByTestId('add-domain-input'), { target: { value: 'new.sasapp' } });
    fireEvent.click(screen.getByTestId('add-domain-submit'));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/admin/api/tenants/t-1/domains', { domain: 'new.sasapp' });
    });
    expect(toast.success).toHaveBeenCalledWith('Domain added successfully');
    expect(onTenantUpdated).toHaveBeenCalledWith(updatedTenant);
  });

  test('shows validation error toast when adding an occupied domain', async () => {
    api.post.mockRejectedValue({
      response: { data: { errors: { domain: ["The domain 'new.sasapp' is already in use by 'Other Corp'."] } } },
    });

    renderWithProviders(
      <DomainModal tenant={activeTenant} onClose={vi.fn()} onTenantUpdated={vi.fn()} />
    );

    fireEvent.change(screen.getByTestId('add-domain-input'), { target: { value: 'new.sasapp' } });
    fireEvent.click(screen.getByTestId('add-domain-submit'));

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledWith("The domain 'new.sasapp' is already in use by 'Other Corp'.");
    });
  });

  test('removes a secondary domain', async () => {
    const onTenantUpdated = vi.fn();
    const updatedTenant = {
      ...activeTenant,
      all_domains: activeTenant.all_domains.filter(d => d.id !== 2),
    };
    api.delete.mockResolvedValue({ data: { tenant: updatedTenant } });

    renderWithProviders(
      <DomainModal tenant={activeTenant} onClose={vi.fn()} onTenantUpdated={onTenantUpdated} />
    );

    fireEvent.click(screen.getByTestId('remove-domain-acme-backup.sasapp'));

    await waitFor(() => {
      expect(api.delete).toHaveBeenCalledWith('/admin/api/tenants/t-1/domains/2');
    });
    expect(toast.success).toHaveBeenCalledWith('Domain removed successfully');
    expect(onTenantUpdated).toHaveBeenCalledWith(updatedTenant);
  });

  test('does not render a remove button on the primary domain', () => {
    renderWithProviders(
      <DomainModal tenant={activeTenant} onClose={vi.fn()} onTenantUpdated={vi.fn()} />
    );

    expect(screen.queryByTestId('remove-domain-acme.sasapp')).not.toBeInTheDocument();
    expect(screen.getByTestId('remove-domain-acme-backup.sasapp')).toBeInTheDocument();
  });

  test('hides add and remove controls without edit tenants permission', () => {
    useAuthContextMock.mockReturnValue({ permissions: ['view tenants'] });

    renderWithProviders(
      <DomainModal tenant={activeTenant} onClose={vi.fn()} onTenantUpdated={vi.fn()} />
    );

    expect(screen.queryByTestId('add-domain-input')).not.toBeInTheDocument();
    expect(screen.queryByTestId('remove-domain-acme-backup.sasapp')).not.toBeInTheDocument();
    expect(screen.getByText('acme.sasapp')).toBeInTheDocument();
  });
});
