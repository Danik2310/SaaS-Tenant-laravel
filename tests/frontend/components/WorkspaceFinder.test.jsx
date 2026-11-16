import { vi, describe, test, beforeEach, expect } from 'vitest';
import React from 'react';
import { render, screen, fireEvent } from '../test-utils';
import WorkspaceFinder from '@/Components/Marketing/WorkspaceFinder';

const { useFormMock, lastForm } = vi.hoisted(() => {
    const useFormMock = vi.fn();
    const lastForm = () => {
        const results = useFormMock.mock.results;
        return results[results.length - 1]?.value ?? null;
    };
    return { useFormMock, lastForm };
});

vi.mock('@inertiajs/react', () => ({
    useForm: useFormMock,
}));

const mockForm = ({ errors = {}, processing = false } = {}) => {
    useFormMock.mockImplementation((initial) => {
        const [data, setData] = React.useState(initial);

        return {
            data,
            setData: (key, value) => setData((prev) => ({ ...prev, [key]: value })),
            post: vi.fn(),
            processing,
            errors,
        };
    });
};

describe('WorkspaceFinder', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockForm();
        global.route = vi.fn(
            (name) => ({ 'workspace.lookup': '/find-workspace' })[name] ?? `/${name}`
        );
    });

    test('shows the configured suffix in the placeholder', () => {
        render(<WorkspaceFinder suffix="example" />);

        expect(
            screen.getByPlaceholderText('your-shop or your-shop.example')
        ).toBeInTheDocument();
    });

    test('submits the typed address to the lookup route', () => {
        const { container } = render(<WorkspaceFinder />);

        fireEvent.change(screen.getByLabelText('Workspace address'), {
            target: { value: 'acme' },
        });
        fireEvent.submit(container.querySelector('form'));

        const form = lastForm();

        expect(form.post).toHaveBeenCalledWith('/find-workspace');
        expect(form.data.workspace).toBe('acme');
    });

    test('renders an inline error and marks the field invalid', () => {
        mockForm({ errors: { workspace: 'We could not find a workspace at that address.' } });

        render(<WorkspaceFinder />);

        const input = screen.getByLabelText('Workspace address');

        expect(screen.getByRole('alert')).toHaveTextContent(
            'We could not find a workspace at that address.'
        );
        expect(input).toHaveAttribute('aria-invalid', 'true');
        expect(input).toHaveAttribute('aria-describedby', 'workspace-error');
    });

    test('disables the submit button while the lookup runs', () => {
        mockForm({ processing: true });

        render(<WorkspaceFinder />);

        expect(screen.getByRole('button', { name: /opening/i })).toBeDisabled();
    });
});
