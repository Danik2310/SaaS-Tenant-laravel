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

const sessionStorageMock = (() => {
  let store = {};
  return {
    getItem: vi.fn((key) => store[key] || null),
    setItem: vi.fn((key, value) => { store[key] = value; }),
    removeItem: vi.fn((key) => { delete store[key]; }),
    clear: vi.fn(() => { store = {}; }),
  };
})();

describe('GodModeIndicator', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    routerPost.mockClear();
    sessionStorageMock.clear();
    Object.defineProperty(window, 'sessionStorage', { value: sessionStorageMock });
    usePage.mockReturnValue({ props: { impersonation: { active: false } } });
  });

  test('renders nothing when not in God Mode', () => {
    renderWithProviders(<GodModeIndicator />);
    expect(screen.queryByTestId('god-mode-indicator')).not.toBeInTheDocument();
    expect(screen.queryByTestId('god-mode-eye')).not.toBeInTheDocument();
  });

  test('renders tenant name, running-as admin, read-only notice, and blinking red eye when impersonating', () => {
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
    expect(screen.getByTestId('god-mode-eye')).toBeInTheDocument();
    expect(screen.getByTestId('god-mode-eye')).toHaveStyle({
      color: '#ef4444',
      animation: 'god-mode-blink 1.2s ease-in-out infinite',
    });
    expect(screen.getByText(/God Mode — running as Super Admin/)).toBeInTheDocument();
    expect(screen.getByText('Acme Corp')).toBeInTheDocument();
    expect(screen.getByText(/Read-only session/)).toBeInTheDocument();
    expect(screen.getByText('Return to Admin')).toBeInTheDocument();
    expect(sessionStorageMock.setItem).toHaveBeenCalledWith(
      'god_mode_session',
      JSON.stringify({
        admin_name: 'Super Admin',
        tenant_name: 'Acme Corp',
        tenant_id: 'acme',
        read_only: true,
      })
    );
  });

  test('keeps showing the eye from sessionStorage after the server session ends', () => {
    sessionStorageMock.setItem(
      'god_mode_session',
      JSON.stringify({ admin_name: 'Super Admin', tenant_name: 'Acme Corp', tenant_id: 'acme', read_only: false })
    );
    usePage.mockReturnValue({ props: { impersonation: { active: false } } });

    renderWithProviders(<GodModeIndicator />);

    expect(screen.getByTestId('god-mode-indicator')).toBeInTheDocument();
    expect(screen.getByTestId('god-mode-eye')).toBeInTheDocument();
    expect(screen.getByText(/God Mode — running as Super Admin/)).toBeInTheDocument();
    expect(screen.getByText('Acme Corp')).toBeInTheDocument();
  });

  test('loses the sessionStorage fallback if the stored payload is corrupted', () => {
    sessionStorageMock.setItem('god_mode_session', '{not-json');
    usePage.mockReturnValue({ props: { impersonation: { active: false } } });

    renderWithProviders(<GodModeIndicator />);

    expect(screen.queryByTestId('god-mode-indicator')).not.toBeInTheDocument();
    expect(sessionStorageMock.removeItem).toHaveBeenCalledWith('god_mode_session');
  });

  test('clicking Return to Admin posts to god-mode.stop and clears sessionStorage', () => {
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
    expect(sessionStorageMock.removeItem).toHaveBeenCalledWith('god_mode_session');
  });
});