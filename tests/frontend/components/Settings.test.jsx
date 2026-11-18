import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import Settings from '@/modules/shared/settings/Settings';
import { resetCurrencyCatalog } from '@/shared/currencies';

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
  const mockCatalog = {
    base: 'USD',
    settings: {
      mode: 'preset',
      preset: 'global_common',
      enabled_currencies: ['USD', 'EUR'],
      scopes: ['admin_dashboard', 'public_plans', 'tenant_dashboards'],
      admin_currency: 'USD',
      public_currency: 'USD',
      tenant_mode: 'preset',
      tenant_preset: 'global_common',
      tenant_currencies: ['USD'],
    },
    currencies: [
      { code: 'USD', name: 'US Dollar', symbol: '$', active: true, allow_tenant: true, allow_public: true },
      { code: 'EUR', name: 'Euro', symbol: '€', active: true, allow_tenant: true, allow_public: true },
    ],
    presets: {
      global_common: { name: 'Global common', currencies: ['USD', 'EUR'] },
    },
  };

  beforeEach(() => {
    vi.clearAllMocks();
    resetCurrencyCatalog();
    mockApi.get.mockImplementation((url) => {
      if (String(url).includes('/admin/api/currencies')) return Promise.resolve({ data: mockCatalog });
      return Promise.resolve({ data: { settings: mockSettings } });
    });
  });

  it('renders a currency select pre-filled with the stored value', async () => {
    renderWithProviders(<Settings />);

    await waitFor(() => {
      expect(screen.getByText('System Settings')).toBeInTheDocument();
    });

    const adminCurrency = screen.getByLabelText('Admin display currency');
    expect(adminCurrency).toHaveValue('USD — US Dollar');
    expect(screen.getByText('One currency for the admin dashboard.')).toBeInTheDocument();
    expect(screen.getByLabelText('Candidates available to tenants')).toBeInTheDocument();
  });

  it('saves settings including the selected currency', async () => {
    mockApi.put.mockResolvedValue({ data: { success: true } });
    renderWithProviders(<Settings />);

    await waitFor(() => {
      expect(screen.getByText('System Settings')).toBeInTheDocument();
    });

    fireEvent.mouseDown(screen.getByLabelText('Admin display currency'));
    fireEvent.click(await screen.findByRole('option', { name: 'EUR — Euro' }));

    fireEvent.click(screen.getByText('Save All Settings'));

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        '/admin/api/settings',
        expect.objectContaining({
          settings: expect.arrayContaining([
            expect.objectContaining({ key: 'currency_admin', value: 'EUR' }),
          ]),
        })
      );
    });
  });
});
