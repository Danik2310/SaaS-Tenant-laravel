import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import DatabaseModal from '@/modules/landlord/modals/DatabaseModal';

const mockApi = vi.hoisted(() => ({
  get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(),
}));
vi.mock('@/services/api', () => ({ default: mockApi }));

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

const mockDbInfo = {
  name: 'tenant_t1_db',
  connection: 'mysql',
  host: '127.0.0.1',
  port: '3306',
};

const tenant = { id: 't-1', name: 'Acme Corp' };

describe('DatabaseModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    Object.assign(navigator, {
      clipboard: { writeText: vi.fn().mockResolvedValue() },
    });
  });

  test('returns null when tenant is null', () => {
    const { container } = renderWithProviders(
      <DatabaseModal tenant={null} onClose={vi.fn()} />
    );
    expect(container.innerHTML).toBe('');
  });

  test('renders loading spinner on mount', () => {
    mockApi.get.mockImplementation(() => new Promise(() => {}));

    renderWithProviders(
      <DatabaseModal tenant={tenant} onClose={vi.fn()} />
    );

    expect(screen.getByRole('progressbar')).toBeInTheDocument();
  });

  test('fetches database info on mount', () => {
    mockApi.get.mockResolvedValue({ data: { database: mockDbInfo } });

    renderWithProviders(
      <DatabaseModal tenant={tenant} onClose={vi.fn()} />
    );

    expect(mockApi.get).toHaveBeenCalledWith('/admin/api/tenants/t-1/database');
  });

  test('renders database info rows on success', async () => {
    mockApi.get.mockResolvedValue({ data: { database: mockDbInfo } });

    renderWithProviders(
      <DatabaseModal tenant={tenant} onClose={vi.fn()} />
    );

    await waitFor(() => {
      expect(screen.getByText('tenant_t1_db')).toBeInTheDocument();
    });

    expect(screen.getByText('mysql')).toBeInTheDocument();
    expect(screen.getByText('127.0.0.1')).toBeInTheDocument();
    expect(screen.getByText('3306')).toBeInTheDocument();
  });

  test('renders error state on fetch failure', async () => {
    mockApi.get.mockRejectedValue(new Error('Network error'));

    renderWithProviders(
      <DatabaseModal tenant={tenant} onClose={vi.fn()} />
    );

    await waitFor(() => {
      expect(screen.getByText('Failed to fetch database info')).toBeInTheDocument();
    });
  });

  test('shows empty state when no db info returned', async () => {
    mockApi.get.mockResolvedValue({ data: { database: null } });

    renderWithProviders(
      <DatabaseModal tenant={tenant} onClose={vi.fn()} />
    );

    await waitFor(() => {
      expect(screen.getByText('No database information available.')).toBeInTheDocument();
    });
  });

  test('displays tenant name chip', async () => {
    mockApi.get.mockResolvedValue({ data: { database: mockDbInfo } });

    renderWithProviders(
      <DatabaseModal tenant={tenant} onClose={vi.fn()} />
    );

    await waitFor(() => {
      expect(screen.getByText('Acme Corp')).toBeInTheDocument();
    });
  });

  test('InfoRow copy button copies value', async () => {
    mockApi.get.mockResolvedValue({ data: { database: mockDbInfo } });

    renderWithProviders(
      <DatabaseModal tenant={tenant} onClose={vi.fn()} />
    );

    await waitFor(() => {
      expect(screen.getByText('tenant_t1_db')).toBeInTheDocument();
    });

    const copyButtons = screen.getAllByRole('button', { name: /copy/i });
    fireEvent.click(copyButtons[0]);

    await waitFor(() => {
      expect(navigator.clipboard.writeText).toHaveBeenCalledWith('tenant_t1_db');
    });
  });

  test('close button fires onClose', async () => {
    mockApi.get.mockResolvedValue({ data: { database: mockDbInfo } });
    const onClose = vi.fn();

    renderWithProviders(
      <DatabaseModal tenant={tenant} onClose={onClose} />
    );

    await waitFor(() => {
      expect(screen.getByText('tenant_t1_db')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByText('Close'));
    expect(onClose).toHaveBeenCalled();
  });
});
