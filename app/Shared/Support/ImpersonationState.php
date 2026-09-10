<?php

namespace App\Shared\Support;

use Illuminate\Support\Facades\Session;

/**
 * Central authority over the impersonation (god mode) session keys.
 *
 * Impersonation relies on two parallel flags that must always be cleared
 * together: the admin-side marker (`impersonate_tenant`,
 * `impersonate_started_at`) and the tenant-side active session
 * (`impersonation`). Keeping the key list in one place prevents the two from
 * drifting apart and leaving stale state that blocks re-impersonation.
 */
class ImpersonationState
{
    /**
     * All session keys that make up an active impersonation.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return ['impersonate_tenant', 'impersonate_started_at', 'impersonation'];
    }

    /**
     * Forget every impersonation session flag.
     */
    public static function clear(): void
    {
        Session::forget(self::keys());
    }

    /**
     * Whether any impersonation flag is currently present.
     */
    public static function active(): bool
    {
        foreach (self::keys() as $key) {
            if (Session::has($key)) {
                return true;
            }
        }

        return false;
    }
}
