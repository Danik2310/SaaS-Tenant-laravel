import { beforeEach, describe, expect, test, vi } from 'vitest';
import { installAuthenticatedPageRestoreGuard } from '@/utils/authenticatedPageRestore';

describe('installAuthenticatedPageRestoreGuard', () => {
    let target;
    let pageShowHandler;
    let popStateHandler;

    beforeEach(() => {
        target = {
            addEventListener: vi.fn((event, handler) => {
                if (event === 'pageshow') pageShowHandler = handler;
                if (event === 'popstate') popStateHandler = handler;
            }),
            removeEventListener: vi.fn(),
            location: { reload: vi.fn() },
        };
    });

    test('reloads an authenticated page restored from the back-forward cache', () => {
        const router = { page: { props: { auth: { user: { id: 1 } } } } };

        installAuthenticatedPageRestoreGuard(router, target);
        pageShowHandler({ persisted: true });

        expect(target.location.reload).toHaveBeenCalledOnce();
    });

    test('does not reload guest pages or normal page loads', () => {
        installAuthenticatedPageRestoreGuard({ page: { props: { auth: { user: null } } } }, target);
        pageShowHandler({ persisted: true });

        installAuthenticatedPageRestoreGuard({ page: { props: { auth: { user: { id: 1 } } } } }, target);
        pageShowHandler({ persisted: false });

        expect(target.location.reload).not.toHaveBeenCalled();
    });

    test('reloads on Inertia history navigation, which never reaches the server', () => {
        installAuthenticatedPageRestoreGuard({ page: { props: {} } }, target);

        popStateHandler({ state: { props: { auth: { user: { id: 1 } } } } });

        expect(target.location.reload).toHaveBeenCalledOnce();
    });

    test('reloads only once when both restore events fire for one navigation', () => {
        const router = { page: { props: { auth: { user: { id: 1 } } } } };

        installAuthenticatedPageRestoreGuard(router, target);
        popStateHandler({});
        pageShowHandler({ persisted: true });

        expect(target.location.reload).toHaveBeenCalledOnce();
    });

    test('removes both listeners when cleaned up', () => {
        const cleanup = installAuthenticatedPageRestoreGuard({ page: { props: {} } }, target);

        cleanup();

        expect(target.removeEventListener).toHaveBeenCalledWith('pageshow', pageShowHandler);
        expect(target.removeEventListener).toHaveBeenCalledWith('popstate', popStateHandler);
    });
});
