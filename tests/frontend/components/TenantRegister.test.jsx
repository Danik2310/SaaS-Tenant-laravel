import { vi, describe, test, beforeEach, expect } from 'vitest';
import React from 'react';
import { render, screen, fireEvent } from '../test-utils';
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

const registerForm = () => render(<TenantRegister tenant_domain_suffix="sasapp" />);

describe('TenantRegister', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        global.route = vi.fn((name) => (name === 'register.tenant' ? '/register' : `/${name}`));
        global.route.mockImplementation((name) => (name === 'register.tenant' ? '/register' : `/${name}`));
    });

    test('renders the full registration form directly without a plan carousel', () => {
        registerForm();

        expect(screen.queryByTestId('plan-carousel')).not.toBeInTheDocument();
        expect(screen.getByLabelText('Tenant Name')).toBeInTheDocument();
        expect(screen.getByLabelText('Email')).toBeInTheDocument();
        expect(screen.getByText('Business & Contact Information')).toBeInTheDocument();
        expect(screen.getByLabelText('Company Name')).toBeInTheDocument();
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

    test('renders the form immediately when a plan is preselected', () => {
        render(<TenantRegister selected_plan="trial" tenant_domain_suffix="sasapp" />);

        expect(screen.getByLabelText('Tenant Name')).toBeInTheDocument();
        expect(screen.queryByTestId('plan-carousel')).not.toBeInTheDocument();
        expect(lastForm().data.plan).toBe('trial');
    });

    test('shows the workspace address while typing the company name', () => {
        registerForm();

        fireEvent.change(screen.getByLabelText('Company Name'), {
            target: { value: 'Acme Corp' },
        });

        expect(screen.getByText('acme-corp.sasapp')).toBeInTheDocument();
    });

    test('submits to the register.tenant route and preserves the preselected plan', () => {
        const { container } = render(
            <TenantRegister selected_plan="trial" tenant_domain_suffix="sasapp" />
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