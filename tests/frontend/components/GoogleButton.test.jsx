import { describe, test, expect } from 'vitest';
import { render, screen } from '../test-utils';
import GoogleButton from '@/Components/Auth/GoogleButton';

const google = {
    id: 'google',
    label: 'Google',
    url: 'http://localhost/auth/google/redirect?tenant=acme&intent=login',
};

describe('GoogleButton', () => {
    test('renders nothing when no provider is configured', () => {
        const { container } = render(<GoogleButton providers={[]} />);

        expect(container).toBeEmptyDOMElement();
    });

    test('links to the server-built central OAuth URL', () => {
        render(<GoogleButton providers={[google]} />);

        expect(screen.getByRole('link', { name: /continue with google/i })).toHaveAttribute('href', google.url);
    });

    test('separates social sign-in from the email form', () => {
        render(<GoogleButton providers={[google]} />);

        expect(screen.getByText('or')).toBeInTheDocument();
    });

    test('renders a custom label verbatim', () => {
        render(<GoogleButton providers={[google]} label="Sign up with Google" />);

        expect(screen.getByRole('link', { name: /sign up with google/i })).toHaveAttribute('href', google.url);
    });

    test('can drop the email divider on the signup page', () => {
        render(<GoogleButton providers={[google]} divider={false} />);

        expect(screen.queryByText('or')).not.toBeInTheDocument();
    });

    test('can lead with the divider when the form sits above the button', () => {
        render(<GoogleButton providers={[google]} divider="before" />);

        const rule = screen.getByText('or');
        const link = screen.getByRole('link', { name: /continue with google/i });

        expect(rule.compareDocumentPosition(link) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });

    test('uses the dark palette on the ledger signup page', () => {
        render(<GoogleButton providers={[google]} tone="dark" />);

        expect(screen.getByRole('link', { name: /continue with google/i })).toHaveClass('text-white');
    });
});
