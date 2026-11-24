import { beforeEach, describe, expect, test, vi } from 'vitest';
import React from 'react';
import api from '@/services/api';
import { toast } from 'sonner';
import Login from '@/modules/shared/Login';
import { fireEvent, renderWithProviders, screen, waitFor } from '../test-utils';

vi.mock('@/services/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
        interceptors: { request: { use: () => {} }, response: { use: () => {} } },
        defaults: {},
    },
}));

vi.mock('sonner', () => ({
    toast: { error: vi.fn(), success: vi.fn() },
}));

describe('AdminLogin', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    const renderLogin = () => renderWithProviders(<Login />);

    test('uses labelled, accessible fields and a single h1', () => {
        renderLogin();

        expect(screen.getByRole('heading', { level: 1, name: 'Admin' })).toBeInTheDocument();
        expect(screen.getByLabelText(/^Email/)).toHaveAttribute('type', 'email');
        expect(screen.getByLabelText(/^Password/)).toHaveAttribute('type', 'password');
        expect(screen.getByRole('button', { name: /log in/i })).toBeEnabled();
    });

    test('toggles password visibility with an accessible control', () => {
        renderLogin();

        const password = screen.getByLabelText(/^Password/);
        const toggle = screen.getByRole('button', { name: 'Show password' });

        expect(toggle).toHaveAttribute('aria-pressed', 'false');
        fireEvent.click(toggle);

        expect(password).toHaveAttribute('type', 'text');
        expect(screen.getByRole('button', { name: 'Hide password' })).toHaveAttribute('aria-pressed', 'true');
    });

    test('submits credentials to the central login endpoint', async () => {
        api.post.mockResolvedValue({ data: { success: false } });
        const { container } = renderLogin();

        fireEvent.change(screen.getByLabelText(/^Email/), { target: { value: 'admin@example.com' } });
        fireEvent.change(screen.getByLabelText(/^Password/), { target: { value: 'secret' } });
        fireEvent.submit(container.querySelector('form'));

        expect(api.post).toHaveBeenCalledWith('/central/login', {
            email: 'admin@example.com',
            password: 'secret',
        });
    });

    test('surfaces API errors in an alert and a toast', async () => {
        api.post.mockRejectedValue({ response: { data: { message: 'Invalid credentials' } } });
        const { container } = renderLogin();

        fireEvent.submit(container.querySelector('form'));

        await waitFor(() => {
            expect(screen.getByRole('alert')).toHaveTextContent('Invalid credentials');
        });
        expect(toast.error).toHaveBeenCalledWith('Invalid credentials');
    });

    test('disables submit while the request is in flight', async () => {
        let resolveRequest;
        api.post.mockImplementation(() => new Promise((resolve) => {
            resolveRequest = resolve;
        }));
        const { container } = renderLogin();

        fireEvent.submit(container.querySelector('form'));

        expect(screen.getByRole('button', { name: /logging in/i })).toBeDisabled();

        resolveRequest({ data: { success: false } });
        await waitFor(() => {
            expect(screen.getByRole('button', { name: /log in/i })).toBeEnabled();
        });
    });
});
