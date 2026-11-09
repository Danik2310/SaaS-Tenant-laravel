<?php

declare(strict_types=1);

namespace App\Tenants\Support;

use App\Models\Tenant;
use App\Models\User;
use App\Tenants\Notifications\TenantEmailVerification;
use Illuminate\Support\Facades\Log;

/**
 * Sends the branded owner-facing verification mail.
 *
 * Shared by the `Registered` listener (the initial signup mail) and the
 * confirmation-waiting screen's resend action so both paths use one template
 * and one signed link builder. Failures are logged and reported as `false`
 * instead of thrown: provisioning and resend both need to keep going when the
 * mail transport is down.
 */
final class TenantVerificationMailer
{
    public static function send(User $user, Tenant $tenant): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $url = TenantVerificationUrl::for($user, $tenant);

        if ($url === null) {
            Log::error('Cannot send tenant verification email: tenant has no domain', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->getKey(),
            ]);

            return false;
        }

        try {
            $user->notify(new TenantEmailVerification(
                $url,
                (string) $tenant->name,
                (string) $tenant->domain,
                TenantVerificationUrl::EXPIRY_MINUTES,
            ));

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send tenant verification email', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
