<?php

declare(strict_types=1);

namespace App\Tenants\Listeners;

use App\Models\Tenant;
use App\Models\User;
use App\Tenants\Notifications\TenantEmailVerification;
use App\Tenants\Support\TenantVerificationUrl;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Log;

/**
 * Sends the branded tenant-owner verification mail.
 *
 * Replaces the framework listener in `EventServiceProvider::$listen` so the
 * central signup (where the owner is created by a queue job with no tenant
 * request) and the in-tenant self-service signup share one email. The job
 * fires `Registered` from inside `tenancy()->initialize()`.
 */
class SendTenantVerificationEmail
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || $user->hasVerifiedEmail()) {
            return;
        }

        // `Registered` may fire outside tenancy (no current flow does, but the
        // listener is global). Keep the framework behaviour there instead of
        // silently dropping the notification.
        if (! tenancy()->initialized) {
            (new SendEmailVerificationNotification)->handle($event);

            return;
        }

        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            (new SendEmailVerificationNotification)->handle($event);

            return;
        }

        $url = TenantVerificationUrl::for($user, $tenant);

        if ($url === null) {
            Log::error('Cannot send tenant verification email: tenant has no domain', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->getKey(),
            ]);

            return;
        }

        try {
            $user->notify(new TenantEmailVerification(
                $url,
                (string) $tenant->name,
                (string) $tenant->domain,
                TenantVerificationUrl::EXPIRY_MINUTES,
            ));
        } catch (\Throwable $e) {
            // Provisioning must not fail because mail is down: the owner can
            // still sign in and resend from the verification screen.
            Log::error('Failed to send tenant verification email', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
