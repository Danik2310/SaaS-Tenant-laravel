<?php

declare(strict_types=1);

namespace App\Shared\Constants;

/**
 * Registry of external identity providers a tenant user can sign in with.
 *
 * The stored value must stay stable: it is half of the unique key on
 * `oauth_accounts` and is compared on every sign-in.
 */
final class OAuthProvider
{
    public const GOOGLE = 'google';

    /**
     * Providers the tenant login/registration screen may offer, in display
     * order. A provider is only rendered when its client credentials are
     * configured (see OAuthProviderLinks).
     *
     * @var array<int, string>
     */
    public const SUPPORTED = [
        self::GOOGLE,
    ];

    /** Human-readable label for the sign-in button. */
    public static function label(string $provider): string
    {
        return match ($provider) {
            self::GOOGLE => 'Google',
            default => ucfirst($provider),
        };
    }
}
