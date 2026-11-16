import { vi, describe, test, beforeEach, expect } from 'vitest';
import React from 'react';
import { render, screen, fireEvent } from '../../test-utils';
import ResetPassword from '@/Pages/Auth/ResetPassword';

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

    return {
        Head: ({ children }) => ReactActual.createElement(ReactActual.Fragment, null, children),
        Link: ({ href, children, ...rest }) =>
            ReactActual.createElement('a', { href, ...rest }, children),
        useForm: useFormMock,
    };
});

const mockForm = ({ errors = {}, processing = false } = {}) => {
    useFormMock.mockImplementation((initial) => {
        const [data, setData] = React.useState(initial);

        return {
            data,
            setData: (key, value) => setData((prev) => ({ ...prev, [key]: value })),
            post: vi.fn(),
            processing,
            errors,
            reset: vi.fn(),
        };
    });
};

const renderReset = (props = {}) =>
    render(<ResetPassword token="reset-token" email="jane@acme.test" {...props} />);

const fill = (label, value) => fireEvent.change(screen.getByLabelText(label), { target: { value } });

describe('ResetPassword', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockForm();
        global.route = vi.fn((name) => ({ 'password.store': '/reset-password' })[name] ?? `/${name}`);
    });

    test('asks for a new password and its confirmation only', () => {
        const { container } = renderReset();

        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Set a new password');
        expect(screen.getByLabelText('New Password')).toHaveAttribute('type', 'password');
        expect(screen.getByLabelText('Confirm Password')).toHaveAttribute('type', 'password');
        expect(container.querySelectorAll('form input')).toHaveLength(2);
    });

    test('shows the address the reset link was issued for', () => {
        renderReset();

        expect(screen.getByTestId('reset-email')).toHaveTextContent('jane@acme.test');
    });

    test('ticks the requirements as the password becomes compliant', () => {
        renderReset();

        const symbol = screen.getByTestId('password-rule-symbol');
        expect(symbol).toHaveAttribute('data-met', 'false');

        fill('New Password', 'Str0ng!Passw0rd');

        expect(screen.getByTestId('password-rule-length')).toHaveAttribute('data-met', 'true');
        expect(screen.getByTestId('password-rule-lowercase')).toHaveAttribute('data-met', 'true');
        expect(screen.getByTestId('password-rule-uppercase')).toHaveAttribute('data-met', 'true');
        expect(screen.getByTestId('password-rule-number')).toHaveAttribute('data-met', 'true');
        expect(screen.getByTestId('password-rule-symbol')).toHaveAttribute('data-met', 'true');
    });

    test('reports when the confirmation matches', () => {
        renderReset();

        fill('New Password', 'Str0ng!Passw0rd');
        fill('Confirm Password', 'Str0ng!Passw0rd');

        expect(screen.getByTestId('password-match-status')).toHaveTextContent('Passwords match');

        fill('Confirm Password', 'Str0ng!Passwor');

        expect(screen.getByTestId('password-match-status')).toHaveTextContent(
            'Passwords do not match yet'
        );
    });

    test('blocks a password that misses the policy and says which rule failed', () => {
        const { container } = renderReset();

        fill('New Password', 'lowercaseonly1');
        fill('Confirm Password', 'lowercaseonly1');
        fireEvent.submit(container.querySelector('form'));

        expect(lastForm().post).not.toHaveBeenCalled();
        expect(screen.getByText('Add an uppercase letter.')).toBeInTheDocument();
    });

    test('blocks a confirmation that does not match', () => {
        const { container } = renderReset();

        fill('New Password', 'Str0ng!Passw0rd');
        fill('Confirm Password', 'Str0ng!Passwor');
        fireEvent.submit(container.querySelector('form'));

        expect(lastForm().post).not.toHaveBeenCalled();
        expect(screen.getByText('Passwords do not match.')).toBeInTheDocument();
    });

    test('submits the token, address and compliant password to the reset route', () => {
        const { container } = renderReset();

        fill('New Password', 'Str0ng!Passw0rd');
        fill('Confirm Password', 'Str0ng!Passw0rd');
        const form = lastForm();
        fireEvent.submit(container.querySelector('form'));

        expect(form.post).toHaveBeenCalledWith('/reset-password');
        expect(form.data).toMatchObject({
            token: 'reset-token',
            email: 'jane@acme.test',
            password: 'Str0ng!Passw0rd',
            password_confirmation: 'Str0ng!Passw0rd',
        });
    });

    test('surfaces a server-side password error', () => {
        mockForm({ errors: { password: 'The password has been breached in a data leak.' } });
        renderReset();

        expect(
            screen.getByText('The password has been breached in a data leak.')
        ).toBeInTheDocument();
    });
});
