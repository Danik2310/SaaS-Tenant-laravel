<?php

declare(strict_types=1);

namespace App\Shared\Support;

use App\Tenants\Support\OAuthHandoffUrl;
use Illuminate\Http\Request;

/**
 * Stable error codes for a failed Google sign-in, and their user-facing copy.
 *
 * The code travels from the central callback to the tenant login screen as
 * `?oauth_error=` on a **signed** URL ({@see OAuthHandoffUrl::errorFor()}),
 * because a flash message cannot cross the central→tenant host boundary. Codes
 * rather than sentences keep provider/exception internals off the screen and
 * make the copy translatable later.
 *
 * The same codes are reused on the central signup page, where no host boundary
 * is crossed and the plain query value is read through {@see fromCode()}.
 */
final class OAuthCallbackError
{
    /** The user closed the consent screen (or pressed "Cancel"). */
    public const CANCELLED = 'cancelled';

    /** Token exchange or account provisioning failed. */
    public const FAILED = 'failed';

    /** The central OAuth session was lost before the callback arrived. */
    public const EXPIRED = 'expired';

    /** The local account is deactivated. */
    public const INACTIVE = 'inactive';

    /** The provider has not verified the address. */
    public const UNVERIFIED = 'unverified';

    /** The tenant is at its plan's user limit. */
    public const USER_LIMIT = 'user_limit';

    /**
     * A login-intent identity whose email has no local account. Sign-in never
     * creates users; joining a workspace goes through its register screen.
     */
    public const NO_ACCOUNT = 'no_account';

    /** A Google signup address that already owns a workspace. */
    public const ACCOUNT_EXISTS = 'account_exists';

    /** @var array<string, string> */
    private const MESSAGES = [
        self::CANCELLED => 'Google sign-in was cancelled. You can try again or log in with your email.',
        self::FAILED => 'We could not complete Google sign-in. Please try again.',
        self::EXPIRED => 'Your Google sign-in session expired. Please try again.',
        self::INACTIVE => 'This account has been deactivated. Contact your workspace administrator.',
        self::UNVERIFIED => 'Google has not verified that email address, so it cannot be used to sign in.',
        self::USER_LIMIT => 'This workspace has reached its user limit. Ask an administrator to upgrade the plan.',
        self::NO_ACCOUNT => 'No account exists for that Google email. Ask your workspace administrator for an account.',
        self::ACCOUNT_EXISTS => 'An account already exists for that email. Log in to your existing workspace instead.',
    ];

    /**
     * Resolve a code that does not cross a host boundary, such as the
     * `?oauth_error=` the central callback appends to the central signup page.
     * Same-host redirects share a session, but a code still keeps the copy in
     * one place and guarantees an unknown value renders nothing.
     */
    public static function fromCode(mixed $code): ?string
    {
        return is_string($code) ? (self::MESSAGES[$code] ?? null) : null;
    }

    /**
     * Resolve the message carried by a signed `?oauth_error=` parameter.
     *
     * Returns null for a missing, unsigned or unknown code, so an attacker
     * cannot make the login page display arbitrary or misleading copy.
     */
    public static function message(Request $request): ?string
    {
        $code = $request->query('oauth_error');

        if (! is_string($code) || ! $request->hasValidSignature()) {
            return null;
        }

        return self::MESSAGES[$code] ?? null;
    }
}
