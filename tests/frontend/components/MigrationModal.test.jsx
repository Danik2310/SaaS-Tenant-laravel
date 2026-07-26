import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent, waitFor } from '../test-utils';
import MigrationModal from '@/modules/shared/modals/MigrationModal';

const mockApi = vi.hoisted(() => ({
  get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(),
}));
vi.mock('@/services/api', () => ({ default: mockApi }));

const tenant = { id: 't-1', name: 'Acme Corp' };
const runBtn = () => screen.getByRole('button', { name: /run migrations/i });

describe('MigrationModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('returns null when tenant is null', () => {
    const { container } = renderWithProviders(
      <MigrationModal tenant={null} onClose={vi.fn()} />
    );
    expect(container.innerHTML).toBe('');
  });

  test('renders info banner and tenant chip', () => {
    renderWithProviders(
      <MigrationModal tenant={tenant} onClose={vi.fn()} />
    );

    expect(screen.getByText(/Execute pending database migrations/)).toBeInTheDocument();
    expect(screen.getByText('Acme Corp')).toBeInTheDocument();
    expect(runBtn()).toBeInTheDocument();
  });

  test('shows loading state while running migrations', async () => {
    mockApi.post.mockImplementation(() => new Promise(() => {}));

    renderWithProviders(
      <MigrationModal tenant={tenant} onClose={vi.fn()} />
    );

    fireEvent.click(runBtn());

    await waitFor(() => {
      expect(screen.getByText('Running migrations...')).toBeInTheDocument();
    });
    expect(screen.getByRole('button', { name: /running/i })).toBeDisabled();
  });

  test('shows success output after migration', async () => {
    mockApi.post.mockResolvedValue({ data: { output: 'Migration 1 done\nMigration 2 done' } });

    renderWithProviders(
      <MigrationModal tenant={tenant} onClose={vi.fn()} />
    );

    fireEvent.click(runBtn());

    await waitFor(() => {
      expect(screen.getByText('Migrations completed successfully')).toBeInTheDocument();
    });
    expect(screen.getByText(/Migration 1 done/)).toBeInTheDocument();
  });

  test('shows error state on failure', async () => {
    mockApi.post.mockRejectedValue({
      response: { data: { message: 'Migration failed' } },
    });

    renderWithProviders(
      <MigrationModal tenant={tenant} onClose={vi.fn()} />
    );

    fireEvent.click(runBtn());

    await waitFor(() => {
      expect(screen.getByText('Migration failed')).toBeInTheDocument();
    });
  });

  test('shows error with output on failure', async () => {
    mockApi.post.mockRejectedValue({
      response: { data: { message: 'Migration failed', output: 'Error at line 5' } },
    });

    renderWithProviders(
      <MigrationModal tenant={tenant} onClose={vi.fn()} />
    );

    fireEvent.click(runBtn());

    await waitFor(() => {
      expect(screen.getByText('Error at line 5')).toBeInTheDocument();
    });
  });

  test('clear output button resets state', async () => {
    mockApi.post.mockResolvedValue({ data: { output: 'Done' } });

    renderWithProviders(
      <MigrationModal tenant={tenant} onClose={vi.fn()} />
    );

    fireEvent.click(runBtn());

    await waitFor(() => {
      expect(screen.getByText('Done')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByText('Clear output'));

    expect(screen.queryByText('Done')).not.toBeInTheDocument();
  });

  test('Run button is disabled during loading', async () => {
    mockApi.post.mockImplementation(() => new Promise(() => {}));

    renderWithProviders(
      <MigrationModal tenant={tenant} onClose={vi.fn()} />
    );

    fireEvent.click(runBtn());

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /running/i })).toBeDisabled();
    });
  });

  test('calls POST with correct URL', async () => {
    mockApi.post.mockResolvedValue({ data: { output: 'Done' } });

    renderWithProviders(
      <MigrationModal tenant={tenant} onClose={vi.fn()} />
    );

    fireEvent.click(runBtn());

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith('/admin/api/tenants/t-1/migrate');
    });
  });
});
