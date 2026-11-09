<?php

declare(strict_types=1);

namespace App\Tenants\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Server-side cooldown for the confirmation-waiting screen's resend button.
 *
 * The client timer is only a courtesy: the server rate-limits on a key tied to
 * the tenant and the normalised owner email, so a reload, a second tab, or a
 * hand-crafted POST cannot send a stream of verification mails. The email is
 * hashed so raw addresses never reach the cache store.
 */
final class TenantVerificationCooldown
{
    /** How long the owner has to wait between resend requests. */
    public const SECONDS = 60;

    public static function start(Tenant $tenant, string $email): void
    {
        RateLimiter::hit(self::key($tenant, $email), self::SECONDS);
    }

    public static function remaining(Tenant $tenant, string $email): int
    {
        return RateLimiter::availableIn(self::key($tenant, $email));
    }

    public static function isActive(Tenant $tenant, string $email): bool
    {
        return RateLimiter::tooManyAttempts(self::key($tenant, $email), 1);
    }

    private static function key(Tenant $tenant, string $email): string
    {
        return 'tenant_verification_resend:'
            .$tenant->getTenantKey()
            .':'
            .hash('sha256', strtolower(trim($email)));
    }
}
