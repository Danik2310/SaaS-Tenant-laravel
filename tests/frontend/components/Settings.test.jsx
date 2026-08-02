import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import Settings from '@/modules/shared/settings/Settings';

const mockApi = vi.hoisted(() => ({
  get: vi.fn(),
  put: vi.fn(),
}));

vi.mock('@/services/api', () => ({ default: mockApi }));

vi.mock('sonner', () => ({
  toast: { success: vi.fn(), error: vi.fn() },
}));

const mockSettings = [
  { key: 'app_name', value: 'My SaaS' },
  { key: 'app_description', value: 'A platform' },
  { key: 'support_email', value: 'support@example.com' },
  { key: 'currency', value: 'USD' },
  { key: 'tenant_db_prefix', value: 'tenant_' },
  { key: 'allow_registration', value: 'true' },
  { key: 'maintenance_mode', value: 'false' },
  { key: 'default_plan_id', value: '1' },
];

describe('Settings', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockResolvedValue({ data: { settings: mockSettings } });
  });

  it('renders a currency select pre-filled with the stored value', async () => {
    renderWithProviders(<Settings />);

    await waitFor(() => {
      expect(screen.getByText('System Settings')).toBeInTheDocument();
    });

    const combobox = screen.getByRole('combobox');
    expect(combobox).toBeInTheDocument();
    expect(screen.getByText('US Dollar (USD)')).toBeInTheDocument();
    expect(
      screen.getByText('Display currency for the admin panel. Prices are stored in USD.')
    ).toBeInTheDocument();
  });

  it('saves settings including the selected currency', async () => {
    mockApi.put.mockResolvedValue({ data: { success: true } });
    renderWithProviders(<Settings />);

    await waitFor(() => {
      expect(screen.getByText('System Settings')).toBeInTheDocument();
    });

    fireEvent.mouseDown(screen.getByRole('combobox'));
    fireEvent.click(await screen.findByText('Euro (EUR)'));

    fireEvent.click(screen.getByText('Save All Settings'));

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        '/admin/api/settings',
        expect.objectContaining({
          settings: expect.arrayContaining([
            expect.objectContaining({ key: 'currency', value: 'EUR' }),
          ]),
        })
      );
    });
  });
});
