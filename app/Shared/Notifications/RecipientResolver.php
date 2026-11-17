<?php

declare(strict_types=1);

namespace App\Shared\Notifications;

use App\Models\User;
use App\Shared\Constants\NotificationTypes;
use Illuminate\Support\Collection;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;

/**
 * Resolves who should receive a notification from the type catalog.
 *
 * Targeted types (a passwordless user) carry an explicit user id; broadcast
 * types use the permissions declared in {@see NotificationTypes} so recipients
 * follow the same Spatie grants that gate the underlying screens.
 */
final class RecipientResolver
{
    public function resolve(string $type, ?int $userId = null): Collection
    {
        $permissions = NotificationTypes::definition($type)['permissions'];

        return match (true) {
            $userId !== null => User::query()->whereKey($userId)->get(),
            $permissions === [] => collect(),
            default => $this->usersWithAnyExistingPermission($permissions),
        };
    }

    /**
     * Resolve users holding any declared permission that actually exists in the
     * current tenant. Tenants are not guaranteed to have every catalog row
     * seeded (bare test tenants, partially configured installs), and Spatie's
     * string-based scope throws when any name is missing. Passing only existing
     * Permission models keeps the "any of these" semantics without that throw.
     *
     * @param  array<int, string>  $permissions
     */
    private function usersWithAnyExistingPermission(array $permissions): Collection
    {
        // Spatie's permission scope reads $permission->roles, which is eager
        // loaded here because the app forbids lazy loading in tests.
        $existing = Permission::query()
            ->with('roles')
            ->whereIn('name', $permissions)
            ->where('guard_name', Guard::getDefaultName(User::class))
            ->get();

        if ($existing->isEmpty()) {
            return collect();
        }

        return User::permission($existing)->distinct()->get();
    }
}
