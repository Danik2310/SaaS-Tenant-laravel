const RELOAD_DEDUPE_MS = 500;

/**
 * Re-check the server whenever the browser brings back a page it did not fetch.
 *
 * Logout revokes the JWT and the Laravel session on the server, but a restored
 * page never makes a request, so its cached props and DOM still show the
 * dashboard. Two restore paths exist:
 *
 *  1. Back-forward cache — a full-document restore fires `pageshow` with
 *     `persisted: true`.
 *  2. Inertia history — same-document Back/Forward restores the page from
 *     `history.state` on `popstate`, with no `pageshow` and no HTTP request.
 *
 * A hard reload is deliberately used instead of a client-side check: the server
 * is the only place that knows whether the session is still valid, and its
 * `auth` middleware redirects a guest to the login page.
 */
export function installAuthenticatedPageRestoreGuard(router, target = window) {
    let lastReload = 0;

    const reload = () => {
        const now = Date.now();

        // Both events can fire for the same restore; one reload is enough.
        if (now - lastReload < RELOAD_DEDUPE_MS) {
            return;
        }

        lastReload = now;
        target.location.reload();
    };

    const handlePageShow = (event) => {
        if (event.persisted && router.page?.props?.auth?.user) {
            reload();
        }
    };

    // Inertia restores from history.state without a server round trip, so this
    // path applies to every restored page, not only ones with a user prop.
    const handlePopState = () => reload();

    target.addEventListener('pageshow', handlePageShow);
    target.addEventListener('popstate', handlePopState);

    return () => {
        target.removeEventListener('pageshow', handlePageShow);
        target.removeEventListener('popstate', handlePopState);
    };
}
