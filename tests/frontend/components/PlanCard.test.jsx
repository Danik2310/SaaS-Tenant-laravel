import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PlanCard from '@/Pages/Auth/parts/PlanCard';

const baseDefinitions = {
    inventory: { label: 'Inventory management', sort_order: 1 },
    reports: { label: 'Advanced reports', sort_order: 2 },
    api: { label: 'API access', sort_order: 3 },
    webhooks: { label: 'Webhooks', sort_order: 4 },
    sso: { label: 'Single sign-on', sort_order: 5 },
    bulk: { label: 'Bulk operations', sort_order: 6 },
    custom_domain: { label: 'Custom domain', sort_order: 7 },
};

function makePlan(overrides = {}) {
    return {
        slug: 'growth',
        name: 'Growth',
        price: '15',
        currency: 'USD',
        duration_months: 1,
        limits: {
            users: 5,
            warehouses: 3,
            products: 2000,
            categories: 50,
            storage: 500,
        },
        features: ['inventory', 'reports', 'api', 'webhooks'],
        summary: null,
        footnote: null,
        ...overrides,
    };
}

describe('PlanCard', () => {
    test('renders the plan name as a heading and the price with its period', () => {
        render(<PlanCard plan={makePlan()} featureDefinitions={baseDefinitions} onSelect={() => {}} />);

        const card = screen.getByTestId('plan-card-growth');
        expect(within(card).getByRole('heading', { level: 3, name: 'Growth' })).toBeInTheDocument();
        expect(card).toHaveTextContent('$15');
        expect(card).toHaveTextContent('per month');
    });

    test('shows "forever" for a free plan and "for 14 days" for the trial', () => {
        const { unmount } = render(
            <PlanCard
                plan={makePlan({ slug: 'free', name: 'Free', price: '0', duration_months: null })}
                featureDefinitions={baseDefinitions}
                onSelect={() => {}}
            />
        );
        expect(screen.getByTestId('plan-card-free')).toHaveTextContent('forever');

        unmount();

        render(
            <PlanCard
                plan={makePlan({ slug: 'trial', name: 'Trial', price: '0', duration_months: null })}
                featureDefinitions={baseDefinitions}
                trialDays={14}
                onSelect={() => {}}
            />
        );
        expect(screen.getByTestId('plan-card-trial')).toHaveTextContent('for 14 days');
    });

    test('shows a cadence note only for a multi-month plan', () => {
        const { unmount } = render(
            <PlanCard
                plan={makePlan({ slug: 'enterprise', name: 'Enterprise', price: '99', duration_months: 12 })}
                featureDefinitions={baseDefinitions}
                onSelect={() => {}}
            />
        );
        expect(screen.getByTestId('plan-card-enterprise')).toHaveTextContent('about $8.25 a month, billed yearly');

        unmount();

        render(<PlanCard plan={makePlan()} featureDefinitions={baseDefinitions} onSelect={() => {}} />);
        expect(screen.getByTestId('plan-card-growth')).not.toHaveTextContent('billed');
    });

    test('renders the plan summary copy when the prop is present', () => {
        render(
            <PlanCard
                plan={makePlan({ summary: 'Every stock movement recorded for a growing shop.' })}
                featureDefinitions={baseDefinitions}
                onSelect={() => {}}
            />
        );
        expect(screen.getByTestId('plan-card-growth')).toHaveTextContent('Every stock movement recorded for a growing shop.');
    });

    test('omits the summary and footnote when the props are absent', () => {
        render(<PlanCard plan={makePlan()} featureDefinitions={baseDefinitions} onSelect={() => {}} />);
        const card = screen.getByTestId('plan-card-growth');
        expect(card).not.toHaveTextContent('per month, billed');
    });

    test('renders storage limits with units and promotes 1024 MB to 1 GB', () => {
        render(
            <PlanCard
                plan={makePlan({ limits: { users: 5, warehouses: 1, products: 500, categories: 10, storage: 1024 } })}
                featureDefinitions={baseDefinitions}
                onSelect={() => {}}
            />
        );
        expect(screen.getByTestId('plan-limits-growth')).toHaveTextContent('Storage');
        expect(screen.getByTestId('plan-limits-growth')).toHaveTextContent('1 GB');
    });

    test('renders a null limit as "Unlimited"', () => {
        render(
            <PlanCard
                plan={makePlan({ limits: { users: null, warehouses: null, products: null, categories: null, storage: null } })}
                featureDefinitions={baseDefinitions}
                onSelect={() => {}}
            />
        );
        const limits = screen.getByTestId('plan-limits-growth');
        expect(limits).toHaveTextContent('Unlimited');
    });

    test('keeps the raw feature key when no definition exists', () => {
        render(
            <PlanCard
                plan={makePlan({ features: ['unknown_key'] })}
                featureDefinitions={baseDefinitions}
                onSelect={() => {}}
            />
        );
        expect(screen.getByTestId('plan-card-growth')).toHaveTextContent('unknown_key');
    });

    test('orders features by the catalogue sort_order, not by array order', () => {
        render(
            <PlanCard
                plan={makePlan({ features: ['sso', 'inventory', 'api'] })}
                featureDefinitions={baseDefinitions}
                onSelect={() => {}}
            />
        );
        const card = screen.getByTestId('plan-card-growth');
        expect(card).toHaveTextContent('Inventory management');
        expect(card).toHaveTextContent('API access');
        expect(card).toHaveTextContent('Single sign-on');
    });

    test('hides features past the cap behind a "+N more" disclosure', () => {
        render(
            <PlanCard
                plan={makePlan({ features: ['inventory', 'reports', 'api', 'webhooks', 'sso'] })}
                featureDefinitions={baseDefinitions}
                onSelect={() => {}}
            />
        );
        const card = screen.getByTestId('plan-card-growth');
        expect(card).toHaveTextContent('Inventory management');
        expect(card).toHaveTextContent('Webhooks');
        expect(card).not.toHaveTextContent('Single sign-on');
        expect(card).toHaveTextContent('+1 more');
    });

    test('reveals the hidden features when the disclosure is activated', async () => {
        const user = userEvent.setup();
        render(
            <PlanCard
                plan={makePlan({ features: ['inventory', 'reports', 'api', 'webhooks', 'sso'] })}
                featureDefinitions={baseDefinitions}
                onSelect={() => {}}
            />
        );
        const toggle = screen.getByTestId('plan-features-toggle-growth');
        expect(toggle).toHaveAttribute('aria-expanded', 'false');

        await user.click(toggle);

        expect(toggle).toHaveAttribute('aria-expanded', 'true');
        expect(screen.getByTestId('plan-card-growth')).toHaveTextContent('Single sign-on');
    });

    test('renders no disclosure when every feature fits', () => {
        render(<PlanCard plan={makePlan()} featureDefinitions={baseDefinitions} onSelect={() => {}} />);
        expect(screen.queryByTestId('plan-features-toggle-growth')).not.toBeInTheDocument();
    });

    test('exposes the card CTA by role and reports the plan slug on click', async () => {
        const user = userEvent.setup();
        const onSelect = vi.fn();
        render(<PlanCard plan={makePlan()} featureDefinitions={baseDefinitions} onSelect={onSelect} />);

        const cta = screen.getByRole('button', { name: /sign up/i });
        await user.click(cta);

        expect(onSelect).toHaveBeenCalledWith('growth');
    });

    test('keeps the badge text "Most popular" on the recommended plan only', () => {
        const { unmount } = render(<PlanCard plan={makePlan()} featureDefinitions={baseDefinitions} onSelect={() => {}} />);
        expect(screen.getByTestId('plan-card-growth')).toHaveTextContent('Most popular');
        expect(screen.getByTestId('plan-card-growth')).toHaveAttribute('data-recommended', 'true');

        unmount();

        render(
            <PlanCard
                plan={makePlan({ slug: 'pro', name: 'Pro' })}
                featureDefinitions={baseDefinitions}
                onSelect={() => {}}
            />
        );
        expect(screen.getByTestId('plan-card-pro')).not.toHaveTextContent('Most popular');
        expect(screen.getByTestId('plan-card-pro')).toHaveAttribute('data-recommended', 'false');
    });
});
