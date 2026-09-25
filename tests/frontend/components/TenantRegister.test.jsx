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
    { slug: 'growth', name: 'Growth', price: 15, currency: 'USD', duration_months: 1, can_signup: true, features: [], limits: {} },
];

describe('TenantRegister', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        global.route = vi.fn((name) => (name === 'register.tenant' ? '/register' : `/${name}`));
        global.route.mockImplementation((name) => (name === 'register.tenant' ? '/register' : `/${name}`));
    });

    test('renders the plan carousel before the form', () => {
        render(<TenantRegister plans={plans} tenant_domain_suffix="sasapp" />);

        expect(screen.getByTestId('plan-carousel')).toBeInTheDocument();
        expect(screen.getByTestId('plan-card-trial')).toBeInTheDocument();
        expect(screen.getByTestId('plan-card-free')).toBeInTheDocument();
        expect(screen.getByTestId('plan-card-growth')).toBeInTheDocument();
        expect(screen.queryByLabelText('Tenant Name')).not.toBeInTheDocument();
    });

    test('shows the full form after a plan is selected', () => {
        render(<TenantRegister plans={plans} tenant_domain_suffix="sasapp" />);

        fireEvent.click(within(screen.getByTestId('plan-card-trial')).getByRole('button', { name: /sign up/i }));

        expect(screen.queryByTestId('plan-carousel')).not.toBeInTheDocument();
        expect(lastForm().data.plan).toBe('trial');
        expect(screen.getByLabelText('Tenant Name')).toBeInTheDocument();
        expect(screen.getByLabelText('Email')).toBeInTheDocument();
        expect(screen.getByLabelText('Company Name')).toBeInTheDocument();
        expect(screen.getByLabelText('Workspace Address')).toBeInTheDocument();
        expect(screen.getByText('Business & Contact Information')).toBeInTheDocument();
        expect(screen.getByLabelText('First Name')).toBeInTheDocument();
        expect(screen.getByLabelText('Last Name')).toBeInTheDocument();
        expect(screen.getByLabelText('Phone (optional)')).toBeInTheDocument();
        expect(screen.getByLabelText('Address Line 1')).toBeInTheDocument();
        expect(screen.getByLabelText('Address Line 2')).toBeInTheDocument();
        expect(screen.getByLabelText('City')).toBeInTheDocument();
        expect(screen.getByLabelText('State / Province')).toBeInTheDocument();
        expect(screen.getByLabelText('Postal Code')).toBeInTheDocument();
        expect(screen.getByLabelText('Country')).toBeInTheDocument();
        expect(screen.getByLabelText('Password')).toBeInTheDocument();
        expect(screen.getByLabelText('Confirm Password')).toBeInTheDocument();
        expect(screen.getByRole('checkbox', { name: /terms of service/i })).toBeInTheDocument();
    });

    test('back button returns to the plan carousel', () => {
        render(<TenantRegister plans={plans} tenant_domain_suffix="sasapp" />);

        fireEvent.click(within(screen.getByTestId('plan-card-trial')).getByRole('button', { name: /sign up/i }));
        fireEvent.click(screen.getByRole('button', { name: /← plans/i }));

        expect(screen.getByTestId('plan-carousel')).toBeInTheDocument();
        expect(screen.queryByLabelText('Tenant Name')).not.toBeInTheDocument();
    });

    test('shows the form immediately when a plan is preselected', () => {
        render(<TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />);

        expect(screen.queryByTestId('plan-carousel')).not.toBeInTheDocument();
        expect(screen.getByLabelText('Tenant Name')).toBeInTheDocument();
        expect(lastForm().data.plan).toBe('trial');
    });

    test('prefills the workspace address from the company name', () => {
        render(<TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />);

        fireEvent.change(screen.getByLabelText('Company Name'), {
            target: { value: 'Acme Corp' },
        });

        expect(screen.getByLabelText('Workspace Address')).toHaveValue('acme-corp');
        expect(screen.getByText('acme-corp.sasapp')).toBeInTheDocument();
    });

    test('respects a manually chosen workspace address', () => {
        render(<TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />);

        fireEvent.change(screen.getByLabelText('Company Name'), {
            target: { value: 'Acme Corp' },
        });

        fireEvent.change(screen.getByLabelText('Workspace Address'), {
            target: { value: 'acme-hq' },
        });

        expect(screen.getByText('acme-hq.sasapp')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Company Name'), {
            target: { value: 'Acme Corp LLC' },
        });

        expect(screen.getByLabelText('Workspace Address')).toHaveValue('acme-hq');
    });

    test('submits the chosen subdomain along the preserve preselected plan', () => {
        const { container } = render(
            <TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />
        );

        fireEvent.change(screen.getByLabelText('Company Name'), {
            target: { value: 'Acme Corp' },
        });

        fireEvent.submit(container.querySelector('form'));

        const form = lastForm();
        expect(form).not.toBeNull();
        expect(form.post).toHaveBeenCalledWith('/register');
        expect(form.data.plan).toBe('trial');
        expect(form.data.subdomain).toBe('acme-corp');
    });

    test('submits to the register.tenant route and preserves the preselected plan', () => {
        const { container } = render(
            <TenantRegister plans={plans} selected_plan="trial" tenant_domain_suffix="sasapp" />
        );

        fireEvent.submit(container.querySelector('form'));

        const form = lastForm();
        expect(form).not.toBeNull();
        expect(form.post).toHaveBeenCalledWith('/register');
        expect(form.data.plan).toBe('trial');
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