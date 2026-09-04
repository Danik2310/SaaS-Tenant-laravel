import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent } from '../test-utils';
import GodModeIndicator from '@/Components/GodModeIndicator';

const routerPost = vi.fn();

vi.mock('@inertiajs/react', () => ({
  usePage: vi.fn(),
  router: { post: (...args) => routerPost(...args), visit: vi.fn() },
}));

vi.stubGlobal('route', (name) => `/__route/${name}`);

import { usePage } from '@inertiajs/react';

describe('GodModeIndicator', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    routerPost.mockClear();
    usePage.mockReturnValue({ props: { impersonation: { active: false } } });
  });

  test('renders nothing when not in God Mode', () => {
    renderWithProviders(<GodModeIndicator />);
    expect(screen.queryByTestId('god-mode-indicator')).not.toBeInTheDocument();
  });

  test('renders tenant name and read-only notice when impersonating', () => {
    usePage.mockReturnValue({
      props: {
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

    renderWithProviders(<GodModeIndicator />);

    expect(screen.getByTestId('god-mode-indicator')).toBeInTheDocument();
    expect(screen.getByText(/God Mode — Acme Corp/)).toBeInTheDocument();
    expect(screen.getByText(/Read-only session/)).toBeInTheDocument();
    expect(screen.getByText('Return to Admin')).toBeInTheDocument();
  });

  test('clicking Return to Admin posts to god-mode.stop', () => {
    usePage.mockReturnValue({
      props: {
        impersonation: {
          active: true,
          tenant_name: 'Acme Corp',
          tenant_id: 'acme',
          admin_name: 'Super Admin',
          read_only: false,
          started_at: 123,
        },
      },
    });

    renderWithProviders(<GodModeIndicator />);

    fireEvent.click(screen.getByText('Return to Admin'));
    expect(routerPost).toHaveBeenCalledWith('/__route/god-mode.stop');
  });
});
