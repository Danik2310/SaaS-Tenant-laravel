<?php

declare(strict_types=1);

namespace App\Tenants\Support;

use App\Models\Tenant;
use App\Models\User;
use App\Shared\Support\ForcedUrlRoot;
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

        $scheme = ForcedUrlRoot::scheme();

        return ForcedUrlRoot::run($scheme.'://'.$domain, $scheme, static fn (): string => URL::temporarySignedRoute(
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
        $root = ForcedUrlRoot::centralRoot();
        $scheme = parse_url($root, PHP_URL_SCHEME) ?: 'https';

        return ForcedUrlRoot::run($root, $scheme, static fn (): string => URL::temporarySignedRoute(
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
        $root = ForcedUrlRoot::centralRoot();
        $scheme = parse_url($root, PHP_URL_SCHEME) ?: 'https';

        return ForcedUrlRoot::run($root, $scheme, static fn (): string => URL::temporarySignedRoute(
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

        $scheme = ForcedUrlRoot::scheme();

        return ForcedUrlRoot::run($scheme.'://'.$domain, $scheme, static fn (): string => route('login'));
    }
}
