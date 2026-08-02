import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHookWithProviders, waitFor } from '../test-utils';

const mockApi = vi.hoisted(() => ({
  get: vi.fn(),
}));

vi.mock('@/services/api', () => ({ default: mockApi }));

async function loadMoneyModule() {
  return import('@/shared/money');
}

describe('money utils', () => {
  beforeEach(() => {
    vi.resetModules();
    vi.clearAllMocks();
  });

  it('formats values in USD by default', async () => {
    const { formatMoneyValue } = await loadMoneyModule();
    expect(formatMoneyValue(29)).toBe('$29.00');
  });

  it('converts via the rate for the display currency', async () => {
    const { formatMoneyValue } = await loadMoneyModule();
    expect(formatMoneyValue(100, { base: 'USD', displayCurrency: 'EUR', rates: { EUR: 0.92 } })).toBe('€92.00');
  });

  it('returns an em dash for non-numeric values', async () => {
    const { formatMoneyValue } = await loadMoneyModule();
    expect(formatMoneyValue('abc')).toBe('\u2014');
    expect(formatMoneyValue(undefined)).toBe('\u2014');
    expect(formatMoneyValue(null)).toBe('\u2014');
  });

  it('falls back to USD when the API fails', async () => {
    mockApi.get.mockRejectedValue(new Error('network'));
    const { loadMoneyInfo } = await loadMoneyModule();
    const info = await loadMoneyInfo();
    expect(info.ready).toBe(true);
    expect(info.base).toBe('USD');
    expect(info.displayCurrency).toBe('USD');
  });

  it('uses the live payload when the API succeeds', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        base: 'USD',
        display_currency: 'EUR',
        rates: { EUR: 0.92, GBP: 0.79 },
        is_live: true,
        updated_at: '2026-01-01T00:00:00Z',
      },
    });
    const { loadMoneyInfo, formatMoneyValue } = await loadMoneyModule();
    const info = await loadMoneyInfo();
    expect(info.isLive).toBe(true);
    expect(info.displayCurrency).toBe('EUR');
    expect(formatMoneyValue(50, info)).toBe('€46.00');
  });

  it('caches and dedupes concurrent loads', async () => {
    mockApi.get.mockResolvedValue({ data: { base: 'USD', display_currency: 'EUR', rates: { EUR: 0.92 } } });
    const { loadMoneyInfo } = await loadMoneyModule();
    const [a, b] = await Promise.all([loadMoneyInfo(), loadMoneyInfo()]);
    expect(mockApi.get).toHaveBeenCalledTimes(1);
    expect(a).toEqual(b);
  });

  it('resetMoneyCache forces a refetch', async () => {
    mockApi.get.mockResolvedValue({ data: { base: 'USD', display_currency: 'USD', rates: { USD: 1 } } });
    const { loadMoneyInfo, resetMoneyCache } = await loadMoneyModule();
    await loadMoneyInfo();
    resetMoneyCache();
    await loadMoneyInfo();
    expect(mockApi.get).toHaveBeenCalledTimes(2);
  });

  it('useMoney formats with defaults before info loads', async () => {
    mockApi.get.mockResolvedValue({ data: { base: 'USD', display_currency: 'USD', rates: { USD: 1 } } });
    const { useMoney } = await loadMoneyModule();
    const { result } = renderHookWithProviders(() => useMoney());
    expect(result.current.formatMoney(29)).toBe('$29.00');
    await waitFor(() => expect(result.current.ready).toBe(true));
  });
});
