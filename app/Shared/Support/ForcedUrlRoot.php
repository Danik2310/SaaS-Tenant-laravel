<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Illuminate\Support\Facades\URL;

/**
 * URL generation across the central/tenant host split.
 *
 * This app answers on two kinds of host: central domains (`localhost`,
 * `127.0.0.1`, …) and per-tenant domains (`<slug>.sasapp`). Links are often
 * minted from the *other* side — a queue worker, a tenant request that must
 * point at a central screen, or a central callback that must hand a signed
 * token back to a tenant — so the root has to be forced explicitly.
 *
 * `URL` state is process-global, so {@see run()} restores it in a `finally`:
 * a long-lived worker (or a test process) would otherwise generate every later
 * link for the last host it touched.
 */
final class ForcedUrlRoot
{
    /**
     * The scheme a link should use.
     *
     * Web requests follow their own scheme (so `http://localhost:8000` is not
     * upgraded to https in development); console commands and queue workers
     * have no meaningful request, so they follow `APP_URL` and fall back to
     * https.
     */
    public static function scheme(): string
    {
        if (! app()->runningInConsole()) {
            return request()->getScheme();
        }

        $configured = parse_url((string) config('app.url'), PHP_URL_SCHEME);

        return is_string($configured) && $configured !== '' ? $configured : 'https';
    }

    /**
     * Central root for links that must land on a central domain.
     *
     * A web request on a central host keeps its own host and port, so a visitor
     * who started on `localhost:8000` is not bounced to a different port. Queue
     * workers and tenant-host requests cannot use the current root, so they
     * fall back to `APP_URL` when it points at a central host.
     */
    public static function centralRoot(): string
    {
        $centralDomains = (array) config('tenancy.central_domains', []);
        $appUrl = rtrim((string) config('app.url'), '/');
        $appHost = parse_url($appUrl, PHP_URL_HOST);

        if (! app()->runningInConsole()) {
            $requestHost = request()->getHost();

            if (in_array($requestHost, $centralDomains, true)) {
                return request()->root();
            }
        }

        if (is_string($appHost) && $appHost !== '' && in_array($appHost, $centralDomains, true)) {
            return $appUrl;
        }

        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'https';
        $primary = (string) ($centralDomains[0] ?? ($appHost ?: 'localhost'));

        return $scheme.'://'.$primary;
    }

    /**
     * Run URL generation with a forced root/scheme and restore the previous
     * state afterwards. Only restores the state that was already forced.
     *
     * @param  callable(): string  $callback
     */
    public static function run(string $root, string $scheme, callable $callback): string
    {
        $previousRoot = URL::to('/');
        $previousScheme = parse_url($previousRoot, PHP_URL_SCHEME) ?: 'http';

        // Only re-force the state that was already forced. Restoring a value
        // that merely matched the request root would pin the URL generator to
        // this host for the rest of a long-lived worker or test process.
        $requestRoot = request()->root();
        $hadForcedRoot = $previousRoot !== $requestRoot;
        $hadForcedScheme = $previousScheme !== request()->getScheme();

        try {
            URL::forceRootUrl($root);
            URL::forceScheme($scheme);

            return $callback();
        } finally {
            URL::forceRootUrl($hadForcedRoot ? $previousRoot : null);
            URL::forceScheme($hadForcedScheme ? $previousScheme : null);
        }
    }
}
