<?php

declare(strict_types=1);

namespace App\Tenants\Support;

use App\Http\Controllers\Auth\GoogleOAuthCompleteController;
use App\Models\Tenant;
use App\Models\User;
use App\Shared\Support\ForcedUrlRoot;
use Illuminate\Support\Facades\URL;

/**
 * Signed, single-use URLs that carry the Google sign-in result back to a tenant.
 *
 * The OAuth flow has to run on the central host (Google rejects wildcard
 * redirect URIs and refuses plain http outside loopback), but the session, the
 * user and the JWT all belong to the tenant. A cross-host redirect cannot carry
 * a flash message — each host has its own session cookie — so the only thing
 * that crosses the boundary is a short-lived signature.
 *
 * The JWT is never put in a URL: this link only identifies the user; the tenant
 * endpoint mints the token inside tenant context ({@see GoogleOAuthCompleteController}).
 */
final class OAuthHandoffUrl
{
    /**
     * Long enough for the browser to follow one redirect, short enough that a
     * leaked URL (history, proxy log, referrer) is useless.
     */
    public const EXPIRY_SECONDS = 60;

    /** Signed tenant URL that completes the sign-in for `$user`. */
    public static function for(User $user, Tenant $tenant): ?string
    {
        return self::tenantUrl($tenant, static fn (): string => URL::temporarySignedRoute(
            'auth.google.complete',
            now()->addSeconds(self::EXPIRY_SECONDS),
            ['user' => $user->getKey()],
        ));
    }

    /**
     * Signed tenant login URL that renders `$code` on the login screen.
     *
     * Signed rather than a bare query parameter so a crafted link cannot make
     * the login page claim an arbitrary failure.
     */
    public static function errorFor(Tenant $tenant, string $code): ?string
    {
        return self::tenantUrl($tenant, static fn (): string => URL::temporarySignedRoute(
            'login',
            now()->addSeconds(self::EXPIRY_SECONDS),
            ['oauth_error' => $code],
        ));
    }

    /**
     * @param  callable(): string  $callback
     */
    private static function tenantUrl(Tenant $tenant, callable $callback): ?string
    {
        $domain = (string) $tenant->domain;

        if ($domain === '') {
            return null;
        }

        $scheme = ForcedUrlRoot::scheme();

        return ForcedUrlRoot::run($scheme.'://'.$domain, $scheme, $callback);
    }
}
