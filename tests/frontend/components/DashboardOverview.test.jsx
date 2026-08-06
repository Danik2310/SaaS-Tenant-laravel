import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen } from '../test-utils';
import DashboardOverview from '@/modules/shared/DashboardOverview';

const mockApi = vi.hoisted(() => ({
  get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(),
}));
const { useAuthContextMock } = vi.hoisted(() => ({ useAuthContextMock: vi.fn() }));

vi.mock('@/services/api', () => ({ default: mockApi }));
vi.mock('@/context/AuthContext', () => ({ useAuthContext: () => useAuthContextMock() }));
vi.mock('recharts', () => {
  const { createElement } = require('react');
  const container = ({ children }) => createElement('div', null, children);
  const leaf = () => null;
  return {
    ResponsiveContainer: container,
    AreaChart: container,
    Area: leaf,
    XAxis: leaf,
    YAxis: leaf,
    CartesianGrid: leaf,
    Tooltip: leaf,
    PieChart: container,
    Pie: leaf,
    Cell: leaf,
  };
});

const statsResponse = {
  data: {
    stats: {
      total_tenants: 12,
      active_tenants: 8,
      suspended_tenants: 3,
      deleted_tenants: 1,
      total_staff: 4,
      active_staff: 3,
      total_plans: 2,
    },
    recent_tenants: [],
    tenants_by_month: [],
    status_distribution: [],
  },
};

describe('DashboardOverview', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    Object.defineProperty(window, 'matchMedia', {
      writable: true,
      value: vi.fn().mockImplementation((query) => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: vi.fn(),
        removeListener: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn(),
      })),
    });
  });

  test('fetches dashboard stats when user has view tenants permission', async () => {
    useAuthContextMock.mockReturnValue({ permissions: ['view tenants'] });
    mockApi.get.mockResolvedValue(statsResponse);

    renderWithProviders(<DashboardOverview />);

    expect(await screen.findByText('12')).toBeInTheDocument();
    expect(screen.getByText('Total Tenants')).toBeInTheDocument();
    expect(mockApi.get).toHaveBeenCalledWith('/admin/api/dashboard-stats');
  });

  test('shows fallback alert and skips stats fetch without view tenants permission', async () => {
    useAuthContextMock.mockReturnValue({ permissions: ['manage profile'] });

    renderWithProviders(<DashboardOverview />);

    expect(screen.getByText('Dashboard Overview')).toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveTextContent(
      'You need the view tenants permission to view dashboard statistics.'
    );
    expect(mockApi.get).not.toHaveBeenCalled();
  });

  test('renders Trial status in the status distribution and recent tenants', async () => {
    useAuthContextMock.mockReturnValue({ permissions: ['view tenants'] });
    mockApi.get.mockResolvedValue({
      data: {
        ...statsResponse.data,
        status_distribution: [
          { name: 'Active', value: 8 },
          { name: 'Trial', value: 2 },
          { name: 'Suspended', value: 1 },
          { name: 'Deleted', value: 1 },
        ],
        recent_tenants: [
          {
            name: 'Acme Corp',
            domain: 'acme.test',
            status: 'Trial',
            trial_ends_at: '2026-09-01T00:00:00Z',
            created_at: '2026-08-01',
          },
        ],
      },
    });

    renderWithProviders(<DashboardOverview />);

    expect(await screen.findByText(/Trial \(2\)/)).toBeInTheDocument();
    expect(screen.getByText('Acme Corp')).toBeInTheDocument();
    expect(screen.getByText('Trial')).toBeInTheDocument();
  });
});
