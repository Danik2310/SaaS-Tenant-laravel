<?php

declare(strict_types=1);

namespace App\Shared\Support;

use App\Models\Tenant;
use App\Shared\Constants\OAuthProvider;

/**
 * Builds the "Continue with …" links rendered on the tenant login/register
 * screens, plus the "Sign up with Google" entry on the central workspace
 * signup.
 *
 * The link points at the central OAuth entry point, but it is rendered from a
 * tenant-host request, so the root has to be forced back to a central domain
 * ({@see ForcedUrlRoot::centralRoot()}) for the tenant id to reach the callback
 * in one piece.
 *
 * A provider is only advertised when its credentials are configured, so an
 * unconfigured environment renders the plain email/password form instead of a
 * button that would fail at Google.
 */
final class OAuthProviderLinks
{
    /**
     * @return array<int, array{id: string, label: string, url: string}>
     */
    public static function for(string $intent): array
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant || ! self::googleIsConfigured()) {
            return [];
        }

        return [self::link([
            'tenant' => $tenant->getTenantKey(),
            'intent' => $intent,
        ])];
    }

    /**
     * The entry point for a **new workspace** signup on the central `/register`
     * page. There is no tenant to carry: the callback parks the verified
     * identity in the central session and returns to the plan step.
     *
     * @return array<int, array{id: string, label: string, url: string}>
     */
    public static function forSignup(): array
    {
        if (! self::googleIsConfigured()) {
            return [];
        }

        return [self::link(['flow' => 'signup'])];
    }

    /**
     * @param  array<string, string>  $parameters
     * @return array{id: string, label: string, url: string}
     */
    private static function link(array $parameters): array
    {
        $root = ForcedUrlRoot::centralRoot();
        $scheme = parse_url($root, PHP_URL_SCHEME) ?: 'https';

        return [
            'id' => OAuthProvider::GOOGLE,
            'label' => OAuthProvider::label(OAuthProvider::GOOGLE),
            'url' => ForcedUrlRoot::run($root, $scheme, static fn (): string => route('oauth.google.redirect', $parameters)),
        ];
    }

    /** Whether the Google client is usable (both halves of the credential set). */
    public static function googleIsConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }
}
