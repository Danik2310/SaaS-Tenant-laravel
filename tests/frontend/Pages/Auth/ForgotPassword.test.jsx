import { vi, describe, test, beforeEach, expect } from 'vitest';
import React from 'react';
import { render, screen, fireEvent } from '../../test-utils';
import ForgotPassword from '@/Pages/Auth/ForgotPassword';

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

describe('ForgotPassword', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockForm();
        global.route = vi.fn(
            (name) => ({ login: '/login', 'password.email': '/forgot-password' })[name] ?? `/${name}`
        );
    });

    test('offers a way back to the login form', () => {
        render(<ForgotPassword />);

        expect(screen.getByRole('link', { name: /back to log in/i })).toHaveAttribute(
            'href',
            '/login'
        );
    });

    test('requests the reset link for the entered address', () => {
        const { container } = render(<ForgotPassword />);

        fireEvent.change(screen.getByLabelText('Email'), {
            target: { value: 'jane@acme.test' },
        });
        fireEvent.submit(container.querySelector('form'));

        const form = lastForm();

        expect(form.post).toHaveBeenCalledWith('/forgot-password');
        expect(form.data.email).toBe('jane@acme.test');
    });

    test('confirms that the link was sent', () => {
        render(<ForgotPassword status="We have emailed your password reset link." />);

        expect(screen.getByTestId('flash-status')).toHaveTextContent(
            'We have emailed your password reset link.'
        );
    });
});
