<?php

declare(strict_types=1);

namespace App\Shared\Support;

use App\Shared\Exceptions\OAuthAccountRejectedException;
use Illuminate\Http\Request;

/**
 * Stable codes and copy for the settings "Connect Google" result.
 *
 * The flow crosses central → tenant hosts, so a session flash cannot survive
 * the return trip. A short-lived signed URL carries one of these codes back to
 * the tenant security page instead.
 */
final class OAuthConnectResult
{
    public const CONNECTED = 'connected';

    public const CANCELLED = 'cancelled';

    public const FAILED = 'failed';

    public const EXPIRED = 'expired';

    public const ALREADY_LINKED = 'already_linked';

    public const UNVERIFIED = 'unverified';

    /** @var array<string, string> */
    private const MESSAGES = [
        self::CONNECTED => 'Google account connected.',
        self::CANCELLED => 'Google sign-in was cancelled. No changes were made.',
        self::FAILED => 'We could not connect your Google account. Please try again.',
        self::EXPIRED => 'Your Google connect session expired. Please start again from your security settings.',
        self::ALREADY_LINKED => 'That Google account is already connected to a different user.',
        self::UNVERIFIED => 'Google has not verified that email address, so it cannot be connected.',
    ];

    /** Resolve the copy for a signed `?code=` parameter, or null for junk. */
    public static function message(Request $request): ?string
    {
        $code = $request->query('code');

        if (! is_string($code) || ! $request->hasValidSignature()) {
            return null;
        }

        return self::MESSAGES[$code] ?? null;
    }

    /** Translate a linker rejection into a connect-result code. */
    public static function fromRejection(string $reason): string
    {
        return match ($reason) {
            OAuthAccountRejectedException::ALREADY_LINKED => self::ALREADY_LINKED,
            OAuthAccountRejectedException::UNVERIFIED_EMAIL => self::UNVERIFIED,
            default => self::FAILED,
        };
    }
}
