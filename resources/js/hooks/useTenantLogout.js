import { useCallback } from 'react';
import { router } from '@inertiajs/react';

/**
 * Ends the tenant session from anywhere in the app.
 *
 * An open socket is not revoked by the server-side logout, so disconnect
 * explicitly before dropping the JWT cookie.
 */
export default function useTenantLogout() {
    return useCallback(() => {
        window.Echo?.disconnect?.();
        router.post(route('logout'), {}, { replace: true });
    }, []);
}
