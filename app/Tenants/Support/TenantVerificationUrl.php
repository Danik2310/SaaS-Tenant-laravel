<?php

declare(strict_types=1);

namespace App\Tenants\Support;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Builds the email-verification link for a tenant user.
 *
 * `verification.verify` is only registered (and only reachable) on a tenant
 * domain, but the link is also generated from the provisioning job, which has
 * no tenant request. Without forcing the root, `URL` would sign the central
 * `APP_URL` and the link would 404 on the central domain.
 *
 * `URL` state is process-global, so the root and scheme are restored in a
 * `finally`: a long-lived queue worker would otherwise generate every later
 * link for the last tenant it provisioned.
 */
final class TenantVerificationUrl
{
    /** Mirrors Laravel's default `auth.verification.expire`. */
    public const EXPIRY_MINUTES = 60;

    public static function for(User $user, Tenant $tenant): ?string
    {
        $domain = (string) $tenant->domain;

        if ($domain === '') {
            return null;
        }

        $scheme = self::scheme();
        $previousRoot = URL::to('/');
        $previousScheme = parse_url($previousRoot, PHP_URL_SCHEME) ?: 'http';

        // Only re-force the state that was already forced. Restoring a value
        // that merely matched the request root would pin the URL generator to
        // this host for the rest of a long-lived worker or test process.
        $requestRoot = request()->root();
        $hadForcedRoot = $previousRoot !== $requestRoot;
        $hadForcedScheme = $previousScheme !== request()->getScheme();

        try {
            URL::forceRootUrl($scheme.'://'.$domain);
            URL::forceScheme($scheme);

            return URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(self::EXPIRY_MINUTES),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ],
            );
        } finally {
            URL::forceRootUrl($hadForcedRoot ? $previousRoot : null);
            URL::forceScheme($hadForcedScheme ? $previousScheme : null);
        }
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
}
