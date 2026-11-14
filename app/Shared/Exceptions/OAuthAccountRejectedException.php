<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;

/**
 * A verified Google identity that must not be turned into a session.
 *
 * The reason is a stable code, not a sentence: the controller (and through it
 * the login screen) owns the user-facing copy.
 */
class OAuthAccountRejectedException extends RuntimeException
{
    /** The linked or matching local account was deactivated by an admin. */
    public const INACTIVE = 'inactive';

    /** Google has not verified the address, so it cannot be trusted to link. */
    public const UNVERIFIED_EMAIL = 'unverified_email';

    /** The identity is linked to an account that has been soft-deleted. */
    public const DELETED = 'deleted';

    /** The provider returned no subject id or no email address. */
    public const MALFORMED = 'malformed';

    public function __construct(public readonly string $reason)
    {
        parent::__construct("OAuth sign-in rejected: {$reason}");
    }
}
