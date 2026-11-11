<?php

declare(strict_types=1);

namespace App\Tenants\Support;

use App\Models\Tenant;

/**
 * Public broadcast channel for the tenant email-confirmation waiting screen.
 *
 * The owner has no session yet, so the channel cannot be private. The name is
 * an HMAC of the tenant key and the address being confirmed: without APP_KEY a
 * stranger cannot derive another tenant's channel and subscribe to its
 * verification events. The payload is only `{ verified: true }`, so the channel
 * itself never carries tenant data.
 */
final class TenantVerificationChannel
{
    public static function for(Tenant $tenant, string $email): string
    {
        $subject = $tenant->getTenantKey().'|'.strtolower(trim($email));

        return 'tenant-verification.'.hash_hmac(
            'sha256',
            $subject,
            (string) config('app.key'),
        );
    }
}
