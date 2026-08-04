import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import FeatureFlags from '@/modules/billing/FeatureFlags';

const mockApi = vi.hoisted(() => ({
  get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(),
}));
vi.mock('@/services/api', () => ({ default: mockApi }));

const { useAuthContextMock } = vi.hoisted(() => ({ useAuthContextMock: vi.fn() }));
vi.mock('@/context/AuthContext', () => ({ useAuthContext: () => useAuthContextMock() }));

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
import { toast } from 'sonner';

const mockFlags = [
  { id: 1, key: 'advanced', label: 'Advanced', description: 'Premium inventory features.', is_locked: true, is_active: true, sort_order: 0 },
  { id: 2, key: 'custom_domain', label: 'Custom Domain', description: 'Use your own domain.', is_locked: true, is_active: true, sort_order: 2 },
  { id: 3, key: 'beta_gadget', label: 'Beta Gadget', description: 'Experimental feature.', is_locked: false, is_active: true, sort_order: 10 },
];

const listResponse = {
  data: {
    flags: mockFlags,
    meta: { current_page: 1, last_page: 1, per_page: 10, total: mockFlags.length },
  },
};

describe('FeatureFlags', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    useAuthContextMock.mockReturnValue({ permissions: ['manage feature flags'] });
    mockApi.get.mockResolvedValue(listResponse);
  });

  test('renders feature flags table with rows and lock badges', async () => {
    renderWithProviders(<FeatureFlags />);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith(expect.stringContaining('/admin/api/feature-flags'));
    });

    expect(await screen.findByText('Advanced')).toBeInTheDocument();
    expect(screen.getByText('Beta Gadget')).toBeInTheDocument();
    expect(screen.getAllByText('System').length).toBeGreaterThanOrEqual(2);
    expect(screen.getByText('Custom')).toBeInTheDocument();
  });

  test('opens create dialog and submits new flag', async () => {
    mockApi.post.mockResolvedValueOnce({ data: { flag: { id: 4 } } });

    renderWithProviders(<FeatureFlags />);

    fireEvent.click(await screen.findByText('+ New Feature Flag'));

    expect(screen.getByLabelText(/^Key/)).toBeDisabled();
    expect(screen.queryByLabelText(/^Sort Order/)).not.toBeInTheDocument();

    fireEvent.change(screen.getByLabelText(/^Label/), { target: { value: 'Cargo Tracking' } });
    fireEvent.change(screen.getByLabelText(/^Description/), { target: { value: 'Track shipments.' } });

    fireEvent.click(screen.getByText('Add Flag'));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith('/admin/api/feature-flags', {
        key: 'cargo_tracking',
        label: 'Cargo Tracking',
        description: 'Track shipments.',
        is_active: true,
      });
    });
  });

  test('auto-fills the key from the label and the key is read-only on create', async () => {
    mockApi.post.mockResolvedValueOnce({ data: { flag: { id: 5 } } });

    renderWithProviders(<FeatureFlags />);

    fireEvent.click(await screen.findByText('+ New Feature Flag'));

    expect(screen.getByLabelText(/^Key/)).toBeDisabled();

    fireEvent.change(screen.getByLabelText(/^Label/), { target: { value: 'Warehouse Sync' } });
    expect(screen.getByLabelText(/^Key/)).toHaveValue('warehouse_sync');

    fireEvent.click(screen.getByText('Add Flag'));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith('/admin/api/feature-flags', expect.objectContaining({
        key: 'warehouse_sync',
        label: 'Warehouse Sync',
      }));
    });
  });

  test('editing a custom flag label does not rewrite its key', async () => {
    mockApi.put.mockResolvedValueOnce({ data: { flag: mockFlags[2] } });

    renderWithProviders(<FeatureFlags />);

    await screen.findByText('Beta Gadget');

    fireEvent.click(screen.getAllByLabelText('Edit')[2]);

    fireEvent.change(screen.getByLabelText(/^Label/), { target: { value: 'Beta Gadget Pro' } });
    fireEvent.click(screen.getByText('Save Changes'));

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith('/admin/api/feature-flags/3', {
        key: 'beta_gadget',
        label: 'Beta Gadget Pro',
        description: 'Experimental feature.',
        is_active: true,
        sort_order: 10,
      });
    });
  });

  test('locked flag keeps key disabled and blocks deletion', async () => {
    renderWithProviders(<FeatureFlags />);

    await screen.findByText('Advanced');

    fireEvent.click(screen.getAllByLabelText('Edit')[0]);

    await waitFor(() => {
      expect(screen.getByLabelText(/^Key/)).toBeDisabled();
      expect(screen.getByText('System flag. The key is referenced in code and cannot be renamed or deleted.')).toBeInTheDocument();
    });
  });

  test('editing a locked flag still submits label changes', async () => {
    mockApi.put.mockResolvedValueOnce({ data: { flag: mockFlags[0] } });

    renderWithProviders(<FeatureFlags />);

    await screen.findByText('Advanced');

    fireEvent.click(screen.getAllByLabelText('Edit')[0]);

    fireEvent.change(screen.getByLabelText(/^Label/), { target: { value: 'Advanced Suite' } });
    fireEvent.click(screen.getByText('Save Changes'));

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith('/admin/api/feature-flags/1', {
        key: 'advanced',
        label: 'Advanced Suite',
        description: 'Premium inventory features.',
        is_active: true,
        sort_order: 0,
      });
    });
  });

  test('deletes an unlocked flag after confirmation', async () => {
    mockApi.delete.mockResolvedValueOnce({});

    renderWithProviders(<FeatureFlags />);

    await screen.findByText('Beta Gadget');

    fireEvent.click(screen.getAllByLabelText('Delete')[2]);

    await waitFor(() => {
      expect(screen.getByText('Delete Feature Flag')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByText('Delete'));

    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalledWith('/admin/api/feature-flags/3');
    });
  });

  test('surfaces server rejection when deleting a flag in use', async () => {
    mockApi.delete.mockRejectedValueOnce({
      response: { data: { message: 'This flag is assigned to 2 plan(s). Remove it from those plans first.' } },
    });

    renderWithProviders(<FeatureFlags />);

    await screen.findByText('Beta Gadget');

    fireEvent.click(screen.getAllByLabelText('Delete')[2]);
    await screen.findByText('Delete Feature Flag');
    fireEvent.click(screen.getByText('Delete'));

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledWith('This flag is assigned to 2 plan(s). Remove it from those plans first.');
    });
  });
});
