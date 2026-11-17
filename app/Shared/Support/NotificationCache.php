<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Unread badge cache, scoped to both the tenant and the user.
 *
 * Tagged so a tenant switch (or suspension) cannot leak a count into another
 * workspace. Falls back to an uncached count on drivers without tag support,
 * matching the rest of the tenant cache layer.
 */
final class NotificationCache
{
    public static function unreadCount(int $userId, callable $compute): int
    {
        try {
            return (int) Cache::tags([self::tag()])->remember(self::key($userId), 300, $compute);
        } catch (\BadMethodCallException) {
            return (int) $compute();
        }
    }

    public static function forget(int $userId): void
    {
        try {
            Cache::tags([self::tag()])->forget(self::key($userId));
        } catch (\BadMethodCallException) {
            // Nothing to invalidate on an untaggable store.
        }
    }

    private static function key(int $userId): string
    {
        return "notifications.unread.{$userId}";
    }

    private static function tag(): string
    {
        return 'tenant_'.tenant('id');
    }
}
