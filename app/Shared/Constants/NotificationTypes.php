<?php

declare(strict_types=1);

namespace App\Shared\Constants;

/**
 * Catalog of in-app notification types.
 *
 * The definition array is the single source of truth for level, title and the
 * permissions a user needs to receive each type. Adding a type means adding a
 * row here — no if/else chains in dispatchers, controllers or the frontend.
 */
final class NotificationTypes
{
    public const PASSWORD_NOT_SET = 'password_not_set';

    public const LOW_STOCK = 'low_stock';

    public const LARGE_ORDER = 'large_order';

    public const LEVEL_INFO = 'info';

    public const LEVEL_WARNING = 'warning';

    public const LEVEL_CRITICAL = 'critical';

    /**
     * @return array<string, array{level: string, title: string, permissions: array<int, string>, route: string}>
     */
    private static function definitions(): array
    {
        return [
            self::PASSWORD_NOT_SET => [
                'level' => self::LEVEL_WARNING,
                'title' => 'Set a password for your account',
                'permissions' => [],
                'route' => 'password.request',
            ],
            self::LOW_STOCK => [
                'level' => self::LEVEL_WARNING,
                'title' => 'Stock running low',
                'permissions' => [PermissionNames::MANAGE_INVENTORY, PermissionNames::MANAGE_PRODUCTS],
                'route' => 'tenant.products.index',
            ],
            self::LARGE_ORDER => [
                'level' => self::LEVEL_CRITICAL,
                'title' => 'Large order received',
                'permissions' => [PermissionNames::MANAGE_ORDERS, PermissionNames::MANAGE_PAYMENTS],
                'route' => 'tenant.dashboard',
            ],
        ];
    }

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_keys(self::definitions());
    }

    public static function supports(string $type): bool
    {
        return array_key_exists($type, self::definitions());
    }

    /**
     * @return array{level: string, title: string, permissions: array<int, string>, route: string}
     */
    public static function definition(string $type): array
    {
        return self::definitions()[$type]
            ?? throw new \InvalidArgumentException("Unknown notification type [{$type}].");
    }
}
