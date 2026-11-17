import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import NotificationsBell from '@/Components/NotificationsBell';

const reload = vi.fn();
const patch = vi.fn();
const post = vi.fn();
const visit = vi.fn();
const listen = vi.fn();
const leave = vi.fn();
const disconnect = vi.fn();

vi.mock('@inertiajs/react', () => ({
  usePage: vi.fn(),
  router: {
    reload: (...args) => reload(...args),
    patch: (...args) => patch(...args),
    post: (...args) => post(...args),
    visit: (...args) => visit(...args),
  },
}));

vi.stubGlobal('route', (name, ...args) => `/__route/${name}${args.length ? `/${args.join('/')}` : ''}`);

import { usePage } from '@inertiajs/react';

const notifications = {
  unread_count: 2,
  recent: [
    {
      id: 11,
      type: 'low_stock',
      level: 'warning',
      title: 'Stock running low',
      body: 'Widget has 3 units left.',
      read_at: null,
      data: { action_url: '/products/7/edit' },
    },
    {
      id: 12,
      type: 'large_order',
      level: 'critical',
      title: 'Large order received',
      body: 'Order #9 for 1500.00 was flagged.',
      read_at: '2026-11-17T10:00:00Z',
      data: {},
    },
  ],
};

describe('NotificationsBell', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    delete window.Echo;
    usePage.mockReturnValue({ props: { notifications, notification_channel: 'tenant-notifications.abc' } });
    patch.mockImplementation((url, data, options) => options?.onSuccess?.());
  });

  test('shows the unread count and opens the preview list', () => {
    renderWithProviders(<NotificationsBell />);

    expect(screen.getByTestId('notifications-bell')).toBeInTheDocument();
    expect(screen.getByText('2')).toBeInTheDocument();

    fireEvent.click(screen.getByTestId('notifications-bell'));

    expect(screen.getByTestId('notification-11')).toBeInTheDocument();
    expect(screen.getByTestId('notification-title-11')).toHaveTextContent('Stock running low');
    expect(screen.getByTestId('notifications-mark-all')).toBeInTheDocument();
  });

  test('subscribes to the tenant channel and reloads the shared prop on events', () => {
    const listener = vi.fn().mockReturnValue({ stop: vi.fn() });
    window.Echo = {
      private: vi.fn().mockReturnValue({ listen: (event, callback) => {
        listen(event, callback);
        return listener();
      } }),
      leave,
      disconnect,
    };

    const { unmount } = renderWithProviders(<NotificationsBell />);

    expect(window.Echo.private).toHaveBeenCalledWith('tenant-notifications.abc');
    expect(listen).toHaveBeenCalledWith('.notification.created', expect.any(Function));

    const callback = listen.mock.calls[0][1];
    callback();

    expect(reload).toHaveBeenCalledWith({ only: ['notifications'] });

    unmount();
    expect(leave).toHaveBeenCalledWith('tenant-notifications.abc');
  });

  test('mark all read posts to the bulk endpoint', () => {
    renderWithProviders(<NotificationsBell />);
    fireEvent.click(screen.getByTestId('notifications-bell'));
    fireEvent.click(screen.getByTestId('notifications-mark-all'));

    expect(post).toHaveBeenCalledWith('/__route/tenant.notifications.read-all');
  });

  test('clicking an item marks it read then opens its target', async () => {
    renderWithProviders(<NotificationsBell />);
    fireEvent.click(screen.getByTestId('notifications-bell'));
    fireEvent.click(screen.getByTestId('notification-11'));

    expect(patch).toHaveBeenCalledWith(
      '/__route/tenant.notifications.read/11',
      {},
      expect.objectContaining({ preserveScroll: true }),
    );
    await waitFor(() => expect(visit).toHaveBeenCalledWith('/products/7/edit'));
  });

  test('renders safely when Echo and channel are unavailable', () => {
    usePage.mockReturnValue({ props: { notifications: { unread_count: 0, recent: [] } } });

    renderWithProviders(<NotificationsBell />);
    fireEvent.click(screen.getByTestId('notifications-bell'));

    expect(screen.getByTestId('notifications-empty')).toBeInTheDocument();
    expect(post).not.toHaveBeenCalled();
  });
});
