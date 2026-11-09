<?php

declare(strict_types=1);

namespace App\Tenants\Listeners;

use App\Models\Tenant;
use App\Models\User;
use App\Tenants\Support\TenantVerificationMailer;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;

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

        TenantVerificationMailer::send($user, $tenant);
    }
}
