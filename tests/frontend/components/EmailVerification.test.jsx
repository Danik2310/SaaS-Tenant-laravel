import { vi, describe, test, beforeEach, afterEach, expect } from 'vitest';
import { render, screen, fireEvent, act } from '../test-utils';
import TenantRegisterSuccess from '@/Pages/Auth/TenantRegisterSuccess';
import EmailVerificationExpired from '@/Pages/Auth/EmailVerificationExpired';
import VerifyEmail from '@/Pages/Auth/VerifyEmail';

const { postMock, reloadMock, routerPostMock } = vi.hoisted(() => ({
    postMock: vi.fn(),
    reloadMock: vi.fn(),
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/react', async () => {
    const ReactActual = await import('react');

    return {
        Head: ({ children }) => ReactActual.createElement(ReactActual.Fragment, null, children),
        Link: ({ href, children }) => ReactActual.createElement('a', { href }, children),
        useForm: () => ({ post: postMock, processing: false }),
        router: { reload: reloadMock, post: routerPostMock },
    };
});

describe('email verification pages', () => {
    beforeEach(() => {
        vi.stubGlobal('route', (name) => `/${name}`);
        postMock.mockClear();
        reloadMock.mockClear();
        routerPostMock.mockClear();
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

    describe('TenantRegisterSuccess confirmed state', () => {
        test('shows the verified message and a Go to Login button', () => {
            render(
                <TenantRegisterSuccess
                    domain="acme-corp.sasapp"
                    plan="Trial"
                    trialDays={14}
                    email="owner@acme.test"
                    verified
                    loginUrl="http://acme.sasapp.test/login"
                />
            );

            expect(
                screen.getByRole('heading', { name: /^email verified successfully$/i })
            ).toBeInTheDocument();
            expect(screen.getByText('owner@acme.test')).toBeInTheDocument();
            expect(
                screen.getByText(/you can now sign in to your workspace/i)
            ).toBeInTheDocument();
            expect(
                screen.getByRole('link', { name: /go to login/i })
            ).toHaveAttribute('href', 'http://acme.sasapp.test/login');
        });
    });

    describe('TenantRegisterSuccess waiting state', () => {
        const pendingProps = {
            domain: 'acme-corp.sasapp',
            plan: 'Trial',
            trialDays: 14,
            email: 'owner@acme.test',
            resendUrl: 'http://localhost/register/pending/resend?signature=abc',
            loginUrl: 'http://acme.sasapp.test/login',
        };

        test('offers a resend but no way into the workspace before confirmation', () => {
            render(<TenantRegisterSuccess {...pendingProps} cooldownSeconds={0} />);

            expect(screen.getByText('acme-corp.sasapp')).toBeInTheDocument();
            expect(screen.getByText('owner@acme.test')).toBeInTheDocument();
            expect(
                screen.getByText(/you must confirm your email to continue/i)
            ).toBeInTheDocument();
            expect(
                screen.queryByRole('link', { name: /go to your workspace/i })
            ).not.toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: /resend email/i })
            ).toBeInTheDocument();
        });

        test('posts the signed resend URL', () => {
            render(<TenantRegisterSuccess {...pendingProps} cooldownSeconds={0} />);

            fireEvent.click(screen.getByRole('button', { name: /resend email/i }));

            expect(routerPostMock).toHaveBeenCalledWith(
                pendingProps.resendUrl,
                {},
                expect.objectContaining({ preserveScroll: true })
            );
        });

        test('disables the resend while the server cooldown is running', () => {
            render(<TenantRegisterSuccess {...pendingProps} cooldownSeconds={30} />);

            expect(
                screen.getByRole('button', { name: /resend email in 30s/i })
            ).toBeDisabled();
        });

        test('polls the verified prop while waiting', () => {
            vi.useFakeTimers();

            render(<TenantRegisterSuccess {...pendingProps} cooldownSeconds={0} />);

            act(() => {
                vi.advanceTimersByTime(5000);
            });

            expect(reloadMock).toHaveBeenCalledWith({ only: ['verified'] });
        });

        test('flips to confirmed as soon as the Reverb broadcast arrives', () => {
            const listen = vi.fn();
            const leaveChannel = vi.fn();

            vi.stubGlobal('Echo', {
                channel: vi.fn(() => ({ listen })),
                leaveChannel,
            });

            render(
                <TenantRegisterSuccess
                    {...pendingProps}
                    cooldownSeconds={0}
                    verificationChannel="tenant-verification.test"
                />
            );

            expect(listen).toHaveBeenCalledWith(
                '.tenant.email.verified',
                expect.any(Function)
            );

            act(() => {
                listen.mock.calls[0][1]({ verified: true });
            });

            expect(
                screen.getByRole('heading', { name: /^email verified successfully$/i })
            ).toBeInTheDocument();
            expect(leaveChannel).toHaveBeenCalledWith('tenant-verification.test');
        });

        test('shows the resend result status', () => {
            render(
                <TenantRegisterSuccess
                    {...pendingProps}
                    cooldownSeconds={0}
                    status="verification-link-sent"
                />
            );

            expect(screen.getByRole('status')).toHaveTextContent(
                /new verification link is on its way/i
            );
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
