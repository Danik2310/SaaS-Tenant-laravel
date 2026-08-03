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

  it('converts from base to display currency', async () => {
    const { convertFromBase } = await loadMoneyModule();
    const info = { base: 'USD', displayCurrency: 'EUR', rates: { EUR: 0.92 } };
    expect(convertFromBase(29.99, info)).toBe(27.59);
    expect(convertFromBase('50', info)).toBe(46);
  });

  it('converts from display currency back to base', async () => {
    const { convertToBase } = await loadMoneyModule();
    const info = { base: 'USD', displayCurrency: 'EUR', rates: { EUR: 0.92 } };
    expect(convertToBase(27.59, info)).toBe(29.99);
    expect(convertToBase(46, info)).toBe(50);
  });

  it('round-trips a typed display value to base and back', async () => {
    const { convertToBase, convertFromBase } = await loadMoneyModule();
    const info = { base: 'USD', displayCurrency: 'GBP', rates: { GBP: 0.79 } };
    const usd = convertToBase(39.5, info);
    expect(convertFromBase(usd, info)).toBe(39.5);
  });

  it('falls back to a 1:1 rate when the display currency is missing', async () => {
    const { convertFromBase, convertToBase } = await loadMoneyModule();
    const info = { base: 'USD', displayCurrency: 'EUR', rates: { USD: 1 } };
    expect(convertFromBase(25, info)).toBe(25);
    expect(convertToBase(25, info)).toBe(25);
  });

  it('returns NaN for non-numeric conversion inputs', async () => {
    const { convertFromBase, convertToBase } = await loadMoneyModule();
    expect(Number.isNaN(convertFromBase('abc', {}))).toBe(true);
    expect(Number.isNaN(convertToBase(null, {}))).toBe(true);
    expect(Number.isNaN(convertFromBase(undefined, {}))).toBe(true);
  });

  it('rounds zero-decimal currencies to whole units', async () => {
    const { convertFromBase } = await loadMoneyModule();
    const info = { base: 'USD', displayCurrency: 'JPY', rates: { JPY: 150.5 } };
    expect(convertFromBase(100, info)).toBe(15050);
  });

  it('returns the currency symbol for supported codes', async () => {
    const { currencySymbol } = await loadMoneyModule();
    expect(currencySymbol('EUR')).toBe('€');
    expect(currencySymbol('GBP')).toBe('£');
    expect(currencySymbol('SEK')).toBe('kr');
    expect(currencySymbol('XYZ')).toBe('XYZ');
  });

  it('useMoney exposes converters bound to the live state', async () => {
    mockApi.get.mockResolvedValue({ data: { base: 'USD', display_currency: 'EUR', rates: { EUR: 0.92 } } });
    const { useMoney } = await loadMoneyModule();
    const { result } = renderHookWithProviders(() => useMoney());
    await waitFor(() => expect(result.current.ready).toBe(true));
    expect(result.current.convertToBase(46)).toBe(50);
    expect(result.current.convertFromBase(29.99)).toBe(27.59);
  });
});
