import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent } from '../test-utils';
import TenantLayout from '@/Layouts/TenantLayout';

const routerPost = vi.fn();

vi.mock('@inertiajs/react', () => ({
  usePage: vi.fn(),
  router: { post: (...args) => routerPost(...args), visit: vi.fn() },
  Link: ({ children, ...props }) => <a {...props}>{children}</a>,
}));

vi.stubGlobal('route', (name) => `/__route/${name}`);

import { usePage } from '@inertiajs/react';

const baseProps = {
  auth: { user: { id: 1, name: 'Tenant Admin', email: 'admin@acme.com' } },
  impersonation: { active: false },
};

describe('TenantLayout', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    routerPost.mockClear();
    usePage.mockReturnValue({ props: { ...baseProps } });
  });

  test('renders God Mode indicator with tenant name and stop control when impersonating', () => {
    usePage.mockReturnValue({
      props: {
        ...baseProps,
        impersonation: {
          active: true,
          tenant_name: 'Acme Corp',
          tenant_id: 'acme',
          admin_name: 'Super Admin',
          read_only: true,
          started_at: 123,
        },
      },
    });

    renderWithProviders(
      <TenantLayout>
        <div>Tenant Content</div>
      </TenantLayout>
    );

    expect(screen.getByTestId('god-mode-indicator')).toBeInTheDocument();
    expect(screen.getByText(/God Mode — running as Super Admin/)).toBeInTheDocument();
    expect(screen.getByText('Acme Corp')).toBeInTheDocument();
    expect(screen.getByText(/Read-only session/)).toBeInTheDocument();
    expect(screen.getByText('Return to Admin')).toBeInTheDocument();
  });

  test('clicking Return to Admin in God Mode posts to god-mode.stop', () => {
    usePage.mockReturnValue({
      props: {
        ...baseProps,
        impersonation: {
          active: true,
          tenant_name: 'Acme Corp',
          tenant_id: 'acme',
          admin_name: 'Super Admin',
          read_only: true,
          started_at: 123,
        },
      },
    });

    renderWithProviders(
      <TenantLayout>
        <div>Tenant Content</div>
      </TenantLayout>
    );

    fireEvent.click(screen.getByText('Return to Admin'));
    expect(routerPost).toHaveBeenCalledWith('/__route/god-mode.stop');
  });

  test('does not render God Mode indicator in a normal session', () => {
    renderWithProviders(
      <TenantLayout>
        <div>Tenant Content</div>
      </TenantLayout>
    );

    expect(screen.queryByTestId('god-mode-indicator')).not.toBeInTheDocument();
    expect(screen.getByText('Tenant Content')).toBeInTheDocument();
  });

  test('logging out replaces the current dashboard history entry', () => {
    renderWithProviders(
      <TenantLayout>
        <div>Tenant Content</div>
      </TenantLayout>
    );

    // The mobile and desktop sidebars are both mounted; either one is fine.
    fireEvent.click(screen.getAllByLabelText('Logout')[0]);

    expect(routerPost).toHaveBeenCalledWith('/__route/logout', {}, { replace: true });
  });
});
