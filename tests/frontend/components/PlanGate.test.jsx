import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen } from '../test-utils';
import PlanGate from '@/Components/PlanGate';

vi.mock('@inertiajs/react', () => ({
  usePage: vi.fn(),
}));

import { usePage } from '@inertiajs/react';

const proPlan = {
  id: 2,
  name: 'Pro',
  slug: 'pro',
  price: 29,
  features: ['advanced', 'multi_warehouse'],
  limits: { users: 50, storage: 1024, warehouses: 5, categories: 50, products: 500 },
  is_on_trial: false,
  trial_has_expired: false,
};

const freePlan = {
  id: 1,
  name: 'Free',
  slug: 'free',
  price: 0,
  features: [],
  limits: { users: 5, storage: 100, warehouses: 1, categories: 10, products: 50 },
  is_on_trial: false,
  trial_has_expired: false,
};

describe('PlanGate', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('renders children when feature is available', () => {
    usePage.mockReturnValue({ props: { plan: proPlan } });

    renderWithProviders(
      <PlanGate feature="advanced">
        <div>Advanced Feature Content</div>
      </PlanGate>
    );

    expect(screen.getByText('Advanced Feature Content')).toBeInTheDocument();
  });

  test('hides children when feature is not available', () => {
    usePage.mockReturnValue({ props: { plan: freePlan } });

    renderWithProviders(
      <PlanGate feature="advanced">
        <div>Advanced Feature Content</div>
      </PlanGate>
    );

    expect(screen.queryByText('Advanced Feature Content')).not.toBeInTheDocument();
  });

  test('shows default fallback when feature is missing', () => {
    usePage.mockReturnValue({ props: { plan: freePlan } });

    renderWithProviders(
      <PlanGate feature="advanced">
        <div>Hidden</div>
      </PlanGate>
    );

    expect(screen.getByText('Premium Feature')).toBeInTheDocument();
    expect(screen.getByText(/not available on your current plan/)).toBeInTheDocument();
    expect(screen.getByText('Upgrade Plan')).toBeInTheDocument();
  });

  test('shows custom fallback component', () => {
    usePage.mockReturnValue({ props: { plan: freePlan } });

    function CustomFallback() {
      return <div>Upgrade to Pro!</div>;
    }

    renderWithProviders(
      <PlanGate feature="advanced" fallback={CustomFallback}>
        <div>Hidden</div>
      </PlanGate>
    );

    expect(screen.getByText('Upgrade to Pro!')).toBeInTheDocument();
  });

  test('renders nothing when fallback is null', () => {
    usePage.mockReturnValue({ props: { plan: freePlan } });

    renderWithProviders(
      <PlanGate feature="advanced" fallback={null}>
        <div>Hidden</div>
      </PlanGate>
    );

    expect(screen.queryByText('Hidden')).not.toBeInTheDocument();
    expect(screen.queryByText('Premium Feature')).not.toBeInTheDocument();
  });

  test('renders children when plan meets minimum tier', () => {
    usePage.mockReturnValue({ props: { plan: proPlan } });

    renderWithProviders(
      <PlanGate plan="pro">
        <div>Pro Content</div>
      </PlanGate>
    );

    expect(screen.getByText('Pro Content')).toBeInTheDocument();
  });

  test('hides children when plan is below minimum tier', () => {
    usePage.mockReturnValue({ props: { plan: freePlan } });

    renderWithProviders(
      <PlanGate plan="pro">
        <div>Pro Content</div>
      </PlanGate>
    );

    expect(screen.queryByText('Pro Content')).not.toBeInTheDocument();
  });

  test('invert prop shows children when feature is missing', () => {
    usePage.mockReturnValue({ props: { plan: freePlan } });

    renderWithProviders(
      <PlanGate feature="advanced" invert fallback={null}>
        <div>Upgrade CTA</div>
      </PlanGate>
    );

    expect(screen.getByText('Upgrade CTA')).toBeInTheDocument();
  });

  test('renders children when no plan and no gate', () => {
    usePage.mockReturnValue({ props: { plan: null } });

    renderWithProviders(
      <PlanGate>
        <div>Always visible</div>
      </PlanGate>
    );

    expect(screen.getByText('Always visible')).toBeInTheDocument();
  });
});
