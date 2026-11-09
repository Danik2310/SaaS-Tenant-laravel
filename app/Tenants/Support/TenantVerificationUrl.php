<?php

declare(strict_types=1);

namespace App\Tenants\Support;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Builds signed links for tenant signup and email verification.
 *
 * `verification.verify` is only registered (and only reachable) on a tenant
 * domain, but the link is also generated from the provisioning job, which has
 * no tenant request. Without forcing the root, `URL` would sign the central
 * `APP_URL` and the link would 404 on the central domain. The confirmation
 * screen works the other way around: it lives on the central domain, so its
 * links must be rooted there even when they are minted from a tenant request.
 *
 * `URL` state is process-global, so the root and scheme are restored in a
 * `finally`: a long-lived queue worker would otherwise generate every later
 * link for the last tenant it provisioned.
 */
final class TenantVerificationUrl
{
    /** Mirrors Laravel's default `auth.verification.expire`. */
    public const EXPIRY_MINUTES = 60;

    /** How long the confirmation-waiting screen and its resend action stay valid. */
    public const PENDING_EXPIRY_HOURS = 24;

    public static function for(User $user, Tenant $tenant): ?string
    {
        $domain = (string) $tenant->domain;

        if ($domain === '') {
            return null;
        }

        $scheme = self::scheme();

        return self::withRoot($scheme.'://'.$domain, $scheme, static fn (): string => URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(self::EXPIRY_MINUTES),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ],
        ));
    }

    /**
     * Signed central URL for the "check your email" screen.
     *
     * The signature is the credential: the owner has no session yet, so the
     * URL itself proves the visitor was sent here by a signup or a verified
     * email link. The tenant id and address travel as signed query parameters.
     */
    public static function pendingFor(Tenant $tenant, string $email): ?string
    {
        $root = self::centralRoot();
        $scheme = parse_url($root, PHP_URL_SCHEME) ?: 'https';

        return self::withRoot($root, $scheme, static fn (): string => URL::temporarySignedRoute(
            'register.pending',
            now()->addHours(self::PENDING_EXPIRY_HOURS),
            ['tenant' => $tenant->getTenantKey(), 'email' => $email],
        ));
    }

    /**
     * Signed central POST URL for resending the verification mail.
     *
     * Generated on every pending-page load, so the resend action expires with
     * the page it belongs to.
     */
    public static function resendFor(Tenant $tenant, string $email): ?string
    {
        $root = self::centralRoot();
        $scheme = parse_url($root, PHP_URL_SCHEME) ?: 'https';

        return self::withRoot($root, $scheme, static fn (): string => URL::temporarySignedRoute(
            'register.pending.resend',
            now()->addHours(self::PENDING_EXPIRY_HOURS),
            ['tenant' => $tenant->getTenantKey(), 'email' => $email],
        ));
    }

    /** Tenant login URL shown once the address is confirmed. */
    public static function loginFor(Tenant $tenant): ?string
    {
        $domain = (string) $tenant->domain;

        if ($domain === '') {
            return null;
        }

        $scheme = self::scheme();

        return self::withRoot($scheme.'://'.$domain, $scheme, static fn (): string => route('login'));
    }

    /**
     * The tenant link follows the app's scheme: the request scheme on web
     * requests, and `APP_URL`'s scheme on a queue worker (where the console
     * request would otherwise always report `http`).
     */
    private static function scheme(): string
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
    private static function centralRoot(): string
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
    private static function withRoot(string $root, string $scheme, callable $callback): string
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
