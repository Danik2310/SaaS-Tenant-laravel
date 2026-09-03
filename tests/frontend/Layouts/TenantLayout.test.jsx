import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen } from '../test-utils';
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

  test('renders God Mode banner with tenant name when impersonating', () => {
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

    expect(screen.getByTestId('god-mode-banner')).toBeInTheDocument();
    expect(screen.getByText(/Viewing tenant: Acme Corp/)).toBeInTheDocument();
    expect(screen.getByText(/Read-only session/)).toBeInTheDocument();
    expect(screen.getByText('Return to Admin')).toBeInTheDocument();
  });

  test('does not render God Mode banner in a normal session', () => {
    renderWithProviders(
      <TenantLayout>
        <div>Tenant Content</div>
      </TenantLayout>
    );

    expect(screen.queryByTestId('god-mode-banner')).not.toBeInTheDocument();
    expect(screen.getByText('Tenant Content')).toBeInTheDocument();
  });
});
