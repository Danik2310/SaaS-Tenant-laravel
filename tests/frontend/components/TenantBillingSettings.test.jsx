import { vi } from 'vitest';
import React from 'react';
import { fireEvent, renderWithProviders, screen } from '../test-utils';

const inertiaMocks = vi.hoisted(() => ({
    put: vi.fn(),
    post: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }) => <>{title}</>,
    Link: React.forwardRef(({ href, children, ...props }, ref) => (
        <a ref={ref} href={href} {...props}>{children}</a>
    )),
    router: { post: inertiaMocks.post },
    useForm: (initial) => ({
        data: initial,
        errors: {},
        processing: false,
        setData: vi.fn(),
        put: inertiaMocks.put,
    }),
}));

vi.mock('@/Layouts/TenantLayout', () => ({
    default: ({ children }) => <div>{children}</div>,
}));

import BillingSettings from '@/Pages/Tenant/Settings/Billing';

const billing = {
    billing_email: 'billing@example.com',
    company_name: 'Acme Ltd',
    first_name: 'Ada',
    last_name: 'Lovelace',
    phone: '+1 555 0100',
    address_line1: '1 Main Street',
    address_line2: '',
    city: 'London',
    state: 'London',
    postal_code: 'SW1A 1AA',
    country: 'United Kingdom',
    tax_id: 'GB123456789',
};

const cards = [{
    id: 'pm_test',
    brand: 'Visa',
    last4: '4242',
    exp_month: 12,
    exp_year: 2030,
    is_default: true,
}];

describe('Tenant billing settings', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        global.route = vi.fn((name) => ({
            'tenant.settings.index': '/settings',
            'tenant.settings.billing.update': '/settings/billing',
            'tenant.settings.billing.portal': '/settings/billing/portal',
        })[name] ?? `/${name}`);
    });

    it('shows masked card metadata and no raw card fields', () => {
        renderWithProviders(
            <BillingSettings billing={billing} cards={cards} cardsEnabled />,
        );

        expect(screen.getByText('Visa ending in 4242')).toBeInTheDocument();
        expect(screen.getByText('Expires 12/2030')).toBeInTheDocument();
        expect(screen.getByText('Default')).toBeInTheDocument();
        expect(screen.queryByLabelText(/card number/i)).not.toBeInTheDocument();
        expect(screen.queryByLabelText(/cvc/i)).not.toBeInTheDocument();
        expect(screen.getByTestId('billing-email-input')).toHaveValue('billing@example.com');
    });

    it('submits the billing profile through an Inertia form', () => {
        renderWithProviders(
            <BillingSettings billing={billing} cards={cards} cardsEnabled />,
        );

        fireEvent.click(screen.getByTestId('billing-save-button'));

        expect(inertiaMocks.put).toHaveBeenCalledWith(
            '/settings/billing',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('opens the Stripe customer portal without collecting card details', () => {
        renderWithProviders(
            <BillingSettings billing={billing} cards={cards} cardsEnabled />,
        );

        fireEvent.click(screen.getByTestId('manage-cards-button'));

        expect(inertiaMocks.post).toHaveBeenCalledWith(
            '/settings/billing/portal',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('disables card management when Stripe is not configured', () => {
        renderWithProviders(
            <BillingSettings billing={billing} cards={[]} cardsEnabled={false} />,
        );

        expect(screen.getByTestId('no-cards-message')).toBeInTheDocument();
        expect(screen.getByTestId('manage-cards-button')).toBeDisabled();
    });

    it('shows the current subscription summary when present', () => {
        renderWithProviders(
            <BillingSettings
                billing={billing}
                cards={cards}
                cardsEnabled
                subscription={{
                    plan_name: 'Pro Plan',
                    status: 'active',
                    starts_at: 'Oct 22, 2026',
                    ends_at: 'Nov 22, 2026',
                }}
            />,
        );

        expect(screen.getByTestId('billing-subscription-plan')).toHaveTextContent('Pro Plan');
        expect(screen.getByTestId('billing-subscription-status')).toHaveTextContent('active');
        expect(screen.getByTestId('billing-subscription-end')).toHaveTextContent('Ends Nov 22, 2026');
    });
});
