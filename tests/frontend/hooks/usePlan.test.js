import { vi, describe, it, expect, beforeEach } from 'vitest';
import { renderHook } from '@testing-library/react';
import { usePlan, useFeature, useLimit } from '@/hooks/usePlan';

const mockPlan = {
  id: 2,
  name: 'Pro',
  slug: 'pro',
  price: 29,
  features: ['advanced', 'multi_warehouse'],
  limits: { users: 50, storage: 1024, warehouses: 5, categories: 50, products: 500 },
  is_on_trial: false,
  trial_has_expired: false,
};

vi.mock('@inertiajs/react', () => ({
  usePage: vi.fn(),
}));

import { usePage } from '@inertiajs/react';

describe('usePlan', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('returns plan data', () => {
    usePage.mockReturnValue({ props: { plan: mockPlan } });
    const { result } = renderHook(() => usePlan());

    expect(result.current.plan).toEqual(mockPlan);
    expect(result.current.hasPlan).toBe(true);
  });

  test('returns hasPlan false when no plan', () => {
    usePage.mockReturnValue({ props: { plan: null } });
    const { result } = renderHook(() => usePlan());

    expect(result.current.hasPlan).toBe(false);
  });

  test('hasFeature returns true for existing feature', () => {
    usePage.mockReturnValue({ props: { plan: mockPlan } });
    const { result } = renderHook(() => usePlan());

    expect(result.current.hasFeature('advanced')).toBe(true);
    expect(result.current.hasFeature('multi_warehouse')).toBe(true);
  });

  test('hasFeature returns false for missing feature', () => {
    usePage.mockReturnValue({ props: { plan: mockPlan } });
    const { result } = renderHook(() => usePlan());

    expect(result.current.hasFeature('api_access')).toBe(false);
  });

  test('hasFeature returns false when no plan', () => {
    usePage.mockReturnValue({ props: { plan: null } });
    const { result } = renderHook(() => usePlan());

    expect(result.current.hasFeature('advanced')).toBe(false);
  });

  test('getLimit returns correct value', () => {
    usePage.mockReturnValue({ props: { plan: mockPlan } });
    const { result } = renderHook(() => usePlan());

    expect(result.current.getLimit('users')).toBe(50);
    expect(result.current.getLimit('products')).toBe(500);
  });

  test('getLimit returns 0 for unknown resource', () => {
    usePage.mockReturnValue({ props: { plan: mockPlan } });
    const { result } = renderHook(() => usePlan());

    expect(result.current.getLimit('api_calls')).toBe(0);
  });

  test('getLimit returns 0 when no plan', () => {
    usePage.mockReturnValue({ props: { plan: null } });
    const { result } = renderHook(() => usePlan());

    expect(result.current.getLimit('users')).toBe(0);
  });

  test('isUnlimited returns true for large limits', () => {
    const enterprisePlan = { ...mockPlan, slug: 'enterprise', limits: { ...mockPlan.limits, users: 2147483647 } };
    usePage.mockReturnValue({ props: { plan: enterprisePlan } });
    const { result } = renderHook(() => usePlan());

    expect(result.current.isUnlimited('users')).toBe(true);
    expect(result.current.isUnlimited('products')).toBe(false);
  });

  test('isOnTrial returns correct value', () => {
    usePage.mockReturnValue({ props: { plan: { ...mockPlan, is_on_trial: true } } });
    const { result } = renderHook(() => usePlan());

    expect(result.current.isOnTrial()).toBe(true);
  });

  test('isPlan checks slug', () => {
    usePage.mockReturnValue({ props: { plan: mockPlan } });
    const { result } = renderHook(() => usePlan());

    expect(result.current.isPlan('pro')).toBe(true);
    expect(result.current.isPlan('free')).toBe(false);
  });

  test('isAtLeast compares plan tiers', () => {
    usePage.mockReturnValue({ props: { plan: mockPlan } });
    const { result } = renderHook(() => usePlan());

    expect(result.current.isAtLeast('free')).toBe(true);
    expect(result.current.isAtLeast('pro')).toBe(true);
    expect(result.current.isAtLeast('enterprise')).toBe(false);
  });
});

describe('useFeature', () => {
  test('returns true for valid feature', () => {
    usePage.mockReturnValue({ props: { plan: mockPlan } });
    const { result } = renderHook(() => useFeature('advanced'));
    expect(result.current).toBe(true);
  });
});

describe('useLimit', () => {
  test('returns limit for resource', () => {
    usePage.mockReturnValue({ props: { plan: mockPlan } });
    const { result } = renderHook(() => useLimit('users'));
    expect(result.current).toBe(50);
  });
});
