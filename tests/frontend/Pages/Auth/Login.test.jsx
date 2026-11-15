import { vi, describe, test, beforeEach, expect } from 'vitest';
import React from 'react';
import { render, screen, fireEvent } from '../../test-utils';
import Login from '@/Pages/Auth/Login';

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

/** Installs the Inertia useForm stub. Overridden per-test to assert error states. */
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

const google = {
    id: 'google',
    label: 'Google',
    url: 'http://localhost/auth/google/redirect?tenant=acme&intent=login',
};

describe('Login', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockForm();
        global.route = vi.fn((name) =>
            ({ login: '/login', 'password.request': '/forgot-password' })[name] ?? `/${name}`
        );
    });

    const renderLogin = (props = {}) =>
        render(<Login canResetPassword={true} providers={[]} signupUrl={null} {...props} />);

    test('asks for email and password before anything else', () => {
        renderLogin();

        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Welcome back');
        expect(screen.getByLabelText('Email')).toHaveAttribute('type', 'email');
        expect(screen.getByLabelText('Password')).toHaveAttribute('type', 'password');
        expect(screen.getByRole('checkbox', { name: /remember me/i })).toBeInTheDocument();
    });

    test('submits the credentials to the login route', () => {
        const { container } = renderLogin();

        fireEvent.change(screen.getByLabelText('Email'), {
            target: { value: 'jane@acme.test' },
        });
        fireEvent.change(screen.getByLabelText('Password'), {
            target: { value: 'supersecret' },
        });
        fireEvent.submit(container.querySelector('form'));

        const form = lastForm();

        expect(form.post).toHaveBeenCalledWith('/login');
        expect(form.data.email).toBe('jane@acme.test');
        expect(form.data.password).toBe('supersecret');
    });

    test('offers Google sign-in below the form, separated by the rule', () => {
        renderLogin({ providers: [google] });

        const submit = screen.getByRole('button', { name: /log in/i });
        const rule = screen.getByText('or');
        const link = screen.getByRole('link', { name: /sign in with google/i });

        expect(link).toHaveAttribute('href', google.url);
        // Email → password → Log in → "or" → Google, never the other way round.
        expect(submit.compareDocumentPosition(rule) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
        expect(rule.compareDocumentPosition(link) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });

    test('places the reset prompt under the Google button', () => {
        renderLogin({ providers: [google] });

        const googleLink = screen.getByRole('link', { name: /sign in with google/i });
        const reset = screen.getByRole('link', { name: /forgot your password/i });

        expect(screen.getByText(/don't remember your password\?/i)).toBeInTheDocument();
        expect(reset).toHaveAttribute('href', '/forgot-password');
        expect(
            googleLink.compareDocumentPosition(reset) & Node.DOCUMENT_POSITION_FOLLOWING
        ).toBeTruthy();
    });

    test('drops the Google button and the rule when no provider is configured', () => {
        renderLogin({ providers: [] });

        expect(screen.queryByRole('link', { name: /sign in with google/i })).not.toBeInTheDocument();
        expect(screen.queryByText('or')).not.toBeInTheDocument();
    });

    test('hides the reset prompt when the workspace cannot reset passwords', () => {
        renderLogin({ canResetPassword: false });

        expect(
            screen.queryByRole('link', { name: /forgot your password/i })
        ).not.toBeInTheDocument();
    });

    test('keeps the signup footer on the workspace host', () => {
        renderLogin({
            providers: [google],
            signupUrl: 'http://localhost/register',
        });

        const signup = screen.getByTestId('signup-link');

        expect(signup).toHaveAttribute('href', 'http://localhost/register');
        expect(screen.getByText(/don't have an account\?/i)).toBeInTheDocument();
    });
});
