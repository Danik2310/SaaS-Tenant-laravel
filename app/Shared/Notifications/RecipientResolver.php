<?php

declare(strict_types=1);

namespace App\Shared\Notifications;

use App\Models\User;
use App\Shared\Constants\NotificationTypes;
use Illuminate\Support\Collection;

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
            default => User::permission($permissions)->distinct()->get(),
        };
    }
}
