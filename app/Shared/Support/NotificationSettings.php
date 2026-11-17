<?php

declare(strict_types=1);

namespace App\Shared\Support;

use App\Models\Setting;

/**
 * Tenant-wide notification thresholds stored in the existing settings table.
 *
 * Keys are namespaced so they cannot collide with other tenant settings, and
 * every read goes through here so a missing row has one documented default.
 */
final class NotificationSettings
{
    public const LOW_STOCK_THRESHOLD = 'notifications.low_stock_threshold';

    public const LARGE_ORDER_THRESHOLD = 'notifications.large_order_threshold';

    public const SPIKE_MULTIPLIER = 'notifications.large_order_spike_multiplier';

    public static function keyFor(string $name): string
    {
        return match ($name) {
            'low_stock_threshold' => self::LOW_STOCK_THRESHOLD,
            'large_order_threshold' => self::LARGE_ORDER_THRESHOLD,
            'spike_multiplier' => self::SPIKE_MULTIPLIER,
        };
    }

    /** @return array<string, float|int> */
    public static function current(): array
    {
        return [
            'low_stock_threshold' => self::value(self::LOW_STOCK_THRESHOLD, StockLevels::DEFAULT_LOW_STOCK_THRESHOLD),
            'large_order_threshold' => self::value(self::LARGE_ORDER_THRESHOLD, 0),
            'spike_multiplier' => self::value(self::SPIKE_MULTIPLIER, 3),
        ];
    }

    private static function value(string $key, int|float $default): int|float
    {
        $value = Setting::query()->where('key', $key)->first()?->value;

        return is_numeric($value) ? $value + 0 : $default;
    }
}
