import { vi } from 'vitest';
import React from 'react';
import { render, screen, fireEvent } from '../test-utils';
import TenantRegister from '@/Pages/Auth/TenantRegister';
import TenantRegisterSuccess from '@/Pages/Auth/TenantRegisterSuccess';

const { useFormMock } = vi.hoisted(() => ({ useFormMock: vi.fn() }));

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

const lastForm = () => {
    const results = useFormMock.mock.results;
    return results[results.length - 1]?.value ?? null;
};

describe('TenantRegister', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        global.route = vi.fn((name) => (name === 'register.tenant' ? '/register' : `/${name}`));
    });

    test('renders all registration fields', () => {
        render(<TenantRegister tenant_domain_suffix="sasapp" />);

        expect(screen.getByLabelText('Company name')).toBeInTheDocument();
        expect(screen.getByLabelText('Full name')).toBeInTheDocument();
        expect(screen.getByLabelText('Work email')).toBeInTheDocument();
        expect(screen.getByLabelText('Phone (optional)')).toBeInTheDocument();
        expect(screen.getByLabelText('Password')).toBeInTheDocument();
        expect(screen.getByLabelText('Confirm Password')).toBeInTheDocument();
        expect(screen.getByRole('checkbox', { name: /terms of service/i })).toBeInTheDocument();
    });

    test('shows the workspace address preview while typing the company name', () => {
        render(<TenantRegister tenant_domain_suffix="sasapp" />);

        fireEvent.change(screen.getByLabelText('Company name'), {
            target: { value: 'Acme Corp' },
        });

        expect(screen.getByText('acme-corp.sasapp')).toBeInTheDocument();
    });

    test('submits to the register.tenant route', () => {
        const { container } = render(<TenantRegister tenant_domain_suffix="sasapp" />);

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