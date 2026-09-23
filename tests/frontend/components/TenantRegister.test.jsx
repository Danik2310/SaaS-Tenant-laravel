import { vi, describe, test, beforeEach, expect } from 'vitest';
import React from 'react';
import { render, screen, fireEvent, within } from '../test-utils';
import TenantRegister from '@/Pages/Auth/TenantRegister';
import TenantRegisterSuccess from '@/Pages/Auth/TenantRegisterSuccess';

const { useFormMock, lastForm } = vi.hoisted(() => {
    const useFormMock = vi.fn();
    const lastForm = () => {
        const results = useFormMock.mock.results;
        return results[results.length - 1]?.value ?? null;
    };
    return { useFormMock, lastForm };
});

vi.mock('@inertiajs/react', async () => {
    const ReactActual = await import('react');

    useFormMock.mockImplementation((initial) => {
        const [data, setData] = ReactActual.useState(initial);

        return {
            data,
            setData: (key, value) => setData((prev) => ({ ...prev, [key]: value })),
            post: vi.fn(),
            processing: false,
            errors: {},
            reset: vi.fn(),
        };
    });

    return {
        Head: () => null,
        Link: ({ href, children, ...rest }) =>
            ReactActual.createElement('a', { href, ...rest }, children),
        useForm: useFormMock,
    };
});

const plans = [
    { slug: 'trial', name: 'Trial', price: 0, currency: 'USD', duration_months: 1, can_signup: true, features: [], limits: {} },
    { slug: 'free', name: 'Free', price: 0, currency: 'USD', duration_months: null, can_signup: true, features: [], limits: {} },
    { slug: 'growth', name: 'Growth', price: 15, currency: 'USD', duration_months: 1, can_signup: false, features: [], limits: {} },
];

describe('TenantRegister', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        global.route = vi.fn((name) => (name === 'register.tenant' ? '/register' : `/${name}`));
        global.route.mockImplementation((name) => (name === 'register.tenant' ? '/register' : `/${name}`));
    });

    test('renders the plan card grid before the form', () => {
        render(<TenantRegister plans={plans} tenant_domain_suffix="sasapp" />);

        expect(screen.getByTestId('plan-grid')).toBeInTheDocument();
        expect(screen.getByTestId('plan-card-trial')).toBeInTheDocument();
        expect(screen.getByTestId('plan-card-free')).toBeInTheDocument();
        expect(screen.getByTestId('plan-card-growth')).toBeInTheDocument();
        expect(screen.queryByLabelText('Company name')).not.toBeInTheDocument();
    });

    test('shows the form after a plan is selected', () => {
        render(<TenantRegister plans={plans} tenant_domain_suffix="sasapp" />);

        fireEvent.click(within(screen.getByTestId('plan-card-trial')).getByRole('button', { name: /sign up/i }));

        expect(screen.getByLabelText('Company name')).toBeInTheDocument();
        expect(screen.getByLabelText('Full name')).toBeInTheDocument();
        expect(screen.getByLabelText('Work email')).toBeInTheDocument();
        expect(screen.getByLabelText('Phone (optional)')).toBeInTheDocument();
        expect(screen.getByLabelText('Password')).toBeInTheDocument();
        expect(screen.getByLabelText('Confirm Password')).toBeInTheDocument();
        expect(screen.getByRole('checkbox', { name: /terms of service/i })).toBeInTheDocument();
    });

    test('shows the form immediately when a plan is preselected', () => {
        render(<TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />);

        expect(screen.getByLabelText('Company name')).toBeInTheDocument();
        expect(screen.getByLabelText('Full name')).toBeInTheDocument();
        expect(screen.getByLabelText('Work email')).toBeInTheDocument();
        expect(screen.getByLabelText('Phone (optional)')).toBeInTheDocument();
        expect(screen.getByLabelText('Password')).toBeInTheDocument();
        expect(screen.getByLabelText('Confirm Password')).toBeInTheDocument();
        expect(screen.getByRole('checkbox', { name: /terms of service/i })).toBeInTheDocument();
    });

    test('shows the workspace address while typing the company name', () => {
        render(<TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />);

        fireEvent.change(screen.getByLabelText('Company name'), {
            target: { value: 'Acme Corp' },
        });

        expect(screen.getByText('acme-corp.sasapp')).toBeInTheDocument();
    });

    test('submits to the register.tenant route', () => {
        const { container } = render(<TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />);

        fireEvent.submit(container.querySelector('form'));

        const form = lastForm();
        expect(form).not.toBeNull();
        expect(form.post).toHaveBeenCalledWith('/register');
    });

    test('success page shows the workspace domain and login link', () => {
        render(
            <TenantRegisterSuccess
                domain="acme-corp.sasapp"
                loginUrl="https://acme-corp.sasapp/login"
                plan="Trial"
                trialDays={14}
                email="jane@acme.test"
            />
        );

        expect(screen.getByText('acme-corp.sasapp')).toBeInTheDocument();
        expect(screen.getByText('jane@acme.test')).toBeInTheDocument();

        expect(screen.getByRole('link', { name: /go to your workspace/i })).toHaveAttribute(
            'href',
            'https://acme-corp.sasapp/login'
        );
    });
});
