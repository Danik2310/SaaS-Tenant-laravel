import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent } from '../test-utils';

const mock = vi.hoisted(() => ({ destroy: vi.fn(), connect: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
  useForm: () => ({
    delete: (...args) => mock.destroy(...args),
    post: (...args) => mock.connect(...args),
    processing: false,
  }),
  Link: ({ children, ...props }) => <a {...props}>{children}</a>,
}));

vi.stubGlobal('route', (name, ...args) =>
  args.length ? `/__route/${name}/${args.join('/')}` : `/__route/${name}`,
);

import ConnectedAccountsCard from '@/Components/ConnectedAccountsCard';

const google = { provider: 'google', label: 'Google', email: 'member-google@example.com' };

describe('ConnectedAccountsCard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mock.destroy.mockClear();
    mock.connect.mockClear();
  });

  test('renders the connected account with a disconnect control', () => {
    renderWithProviders(<ConnectedAccountsCard accounts={[google]} />);

    expect(screen.getByText('Google')).toBeInTheDocument();
    expect(screen.getByText(google.email)).toBeInTheDocument();
    expect(screen.getByTestId('disconnect-google')).toBeEnabled();
    expect(screen.queryByTestId('connect-google')).not.toBeInTheDocument();
  });

  test('renders a connect control when no account is connected', () => {
    renderWithProviders(<ConnectedAccountsCard accounts={[]} />);

    const button = screen.getByTestId('connect-google');
    expect(button).toBeEnabled();
    expect(button).toHaveTextContent('Connect');

    fireEvent.click(button);

    expect(mock.connect).toHaveBeenCalledWith(
      '/__route/tenant.settings.connected-accounts.connect/google',
      expect.objectContaining({ preserveScroll: true }),
    );
  });

  test('hides the connect control when the provider is not configured', () => {
    renderWithProviders(<ConnectedAccountsCard accounts={[]} canConnect={false} />);

    expect(screen.queryByTestId('connect-google')).not.toBeInTheDocument();
  });

  test('disables connect and disconnect and links to security when no password is set', () => {
    renderWithProviders(
      <ConnectedAccountsCard accounts={[]} hasPassword={false} setPasswordHref="/settings/security" />,
    );

    expect(screen.getByTestId('passwordless-oauth-warning')).toHaveTextContent(/set one before connecting or disconnecting google/i);
    expect(screen.getByTestId('connect-google')).toBeDisabled();
    expect(screen.getByRole('link', { name: /set a password first/i })).toHaveAttribute('href', '/settings/security');
  });

  test('asks for confirmation before disconnecting', () => {
    renderWithProviders(<ConnectedAccountsCard accounts={[google]} />);

    fireEvent.click(screen.getByTestId('disconnect-google'));

    expect(screen.getByText(/disconnect google\?/i)).toBeInTheDocument();
    expect(mock.destroy).not.toHaveBeenCalled();

    fireEvent.click(screen.getByTestId('confirm-disconnect'));

    expect(mock.destroy).toHaveBeenCalledWith(
      '/__route/tenant.settings.connected-accounts.destroy/google',
      expect.objectContaining({ preserveScroll: true }),
    );
  });

  test('cancelling the dialog does not disconnect', () => {
    renderWithProviders(<ConnectedAccountsCard accounts={[google]} />);

    fireEvent.click(screen.getByTestId('disconnect-google'));
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(mock.destroy).not.toHaveBeenCalled();
  });
});
