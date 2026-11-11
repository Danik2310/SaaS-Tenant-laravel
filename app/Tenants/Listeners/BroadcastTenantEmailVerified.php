<?php

declare(strict_types=1);

namespace App\Tenants\Listeners;

use App\Models\Tenant;
use App\Models\User;
use App\Tenants\Events\TenantEmailVerified;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Log;

/**
 * Projects the owner's tenant-side verification onto the central tenant row and
 * tells any open waiting screen about it.
 *
 * `users.email_verified_at` in the tenant database is authoritative; the central
 * column is written here so central readers can answer the question without
 * initialising tenancy. A broadcast failure is logged, never re-thrown: the
 * verification itself already succeeded and the waiting screen still polls.
 */
class BroadcastTenantEmailVerified
{
    public function handle(Verified $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $user = $event->user;
        $email = strtolower(trim((string) $user->getEmailForVerification()));

        $tenant = tenancy()->initialized ? tenant() : null;

        if (! $tenant instanceof Tenant) {
            $tenant = Tenant::query()->where('email', $email)->first();
        }

        if (! $tenant instanceof Tenant) {
            return;
        }

        // Only the owner address unlocks the workspace. A teammate confirming
        // their own address must not flip the owner's pending screen.
        if (strtolower(trim((string) $tenant->email)) !== $email) {
            return;
        }

        $verifiedAt = $user->email_verified_at ?? now();

        try {
            $tenant->markOwnerEmailVerified($verifiedAt);
        } catch (\Throwable $e) {
            Log::warning('Could not project the tenant email verification', [
                'tenant_id' => $tenant->getTenantKey(),
                'error' => $e->getMessage(),
            ]);
        }

        try {
            event(new TenantEmailVerified(
                $tenant,
                $email,
                $verifiedAt->toIso8601String(),
            ));
        } catch (\Throwable $e) {
            Log::warning('Could not broadcast the tenant email verification', [
                'tenant_id' => $tenant->getTenantKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
