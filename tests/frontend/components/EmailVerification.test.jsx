import { vi, describe, test, beforeEach, afterEach, expect } from 'vitest';
import { render, screen, fireEvent, act } from '../test-utils';
import EmailVerified from '@/Pages/Auth/EmailVerified';
import EmailVerificationExpired from '@/Pages/Auth/EmailVerificationExpired';
import VerifyEmail from '@/Pages/Auth/VerifyEmail';

const { postMock, visitMock } = vi.hoisted(() => ({
    postMock: vi.fn(),
    visitMock: vi.fn(),
}));

vi.mock('@inertiajs/react', async () => {
    const ReactActual = await import('react');

    return {
        Head: ({ children }) => ReactActual.createElement(ReactActual.Fragment, null, children),
        Link: ({ href, children }) => ReactActual.createElement('a', { href }, children),
        useForm: () => ({ post: postMock, processing: false }),
        router: { visit: visitMock },
    };
});

describe('email verification pages', () => {
    beforeEach(() => {
        vi.stubGlobal('route', (name) => `/${name}`);
        postMock.mockClear();
        visitMock.mockClear();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    describe('VerifyEmail gate', () => {
        test('names the address, states the requirement and offers no way in', () => {
            render(<VerifyEmail status={null} email="owner@acme.test" />);

            expect(
                screen.getByRole('heading', { name: /confirm your email/i })
            ).toBeInTheDocument();
            expect(screen.getByText('owner@acme.test')).toBeInTheDocument();
            expect(
                screen.getByText(/you must confirm your email to continue/i)
            ).toBeInTheDocument();
            expect(screen.getByText(/workspace stays locked/i)).toBeInTheDocument();
            expect(
                screen.queryByRole('link', { name: /go to your workspace/i })
            ).not.toBeInTheDocument();
        });

        test('resends the verification link', () => {
            render(<VerifyEmail status={null} email="owner@acme.test" />);

            fireEvent.click(
                screen.getByRole('button', { name: /resend verification email/i })
            );

            expect(postMock).toHaveBeenCalledWith('/verification.send');
        });

        test('confirms the resend only when the status is set', () => {
            const { unmount } = render(
                <VerifyEmail status={null} email="owner@acme.test" />
            );

            expect(screen.queryByRole('status')).not.toBeInTheDocument();
            unmount();

            render(
                <VerifyEmail status="verification-link-sent" email="owner@acme.test" />
            );

            expect(screen.getByRole('status')).toHaveTextContent(/new link is on its way/i);
        });
    });

    describe('EmailVerified result', () => {
        beforeEach(() => {
            vi.useFakeTimers();
        });

        test('shows the confirmed address then redirects to sign in', () => {
            render(
                <EmailVerified
                    email="owner@acme.test"
                    alreadyVerified={false}
                    loginUrl="http://acme.sasapp.test/login"
                />
            );

            expect(
                screen.getByRole('heading', { name: /^email confirmed$/i })
            ).toBeInTheDocument();
            expect(screen.getByText('owner@acme.test')).toBeInTheDocument();
            expect(screen.getByText(/workspace is unlocked/i)).toBeInTheDocument();
            expect(
                screen.getByText(/taking you to the sign-in page/i)
            ).toBeInTheDocument();
            expect(visitMock).not.toHaveBeenCalled();

            act(() => {
                vi.advanceTimersByTime(3000);
            });

            expect(visitMock).toHaveBeenCalledWith('http://acme.sasapp.test/login', {
                replace: true,
            });
        });

        test('says so when the address was confirmed earlier', () => {
            render(
                <EmailVerified
                    email="owner@acme.test"
                    alreadyVerified
                    loginUrl="http://acme.sasapp.test/login"
                />
            );

            expect(
                screen.getByRole('heading', { name: /email already confirmed/i })
            ).toBeInTheDocument();
            expect(screen.getByText(/earlier visit/i)).toBeInTheDocument();
            expect(
                screen.getByRole('link', { name: /sign in now/i })
            ).toHaveAttribute('href', 'http://acme.sasapp.test/login');
        });
    });

    describe('EmailVerificationExpired', () => {
        test('explains the expiry and points at sign in', () => {
            render(<EmailVerificationExpired loginUrl="http://acme.sasapp.test/login" />);

            expect(
                screen.getByRole('heading', { name: /no longer valid/i })
            ).toBeInTheDocument();
            expect(screen.getByText(/expire one hour/i)).toBeInTheDocument();
            expect(
                screen.getByRole('link', { name: /sign in to request a new link/i })
            ).toHaveAttribute('href', 'http://acme.sasapp.test/login');
        });
    });
});
