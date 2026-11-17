<?php

declare(strict_types=1);

namespace App\Shared\Support;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;

/**
 * One source of truth for stock quantities and low-stock thresholds.
 *
 * The dashboard badge, the movement observer and the scheduled sweep must all
 * agree on what "low" means; the effective threshold is a per-product override
 * falling back to a tenant setting and finally to a fixed default.
 */
final class StockLevels
{
    public const DEFAULT_LOW_STOCK_THRESHOLD = 10;

    public const TENANT_THRESHOLD_KEY = NotificationSettings::LOW_STOCK_THRESHOLD;

    public static function tenantThreshold(): int
    {
        $value = Setting::query()->where('key', self::TENANT_THRESHOLD_KEY)->first()?->value;

        return is_numeric($value) && (int) $value >= 0
            ? (int) $value
            : self::DEFAULT_LOW_STOCK_THRESHOLD;
    }

    public static function effectiveThreshold(Product $product): int
    {
        $override = $product->low_stock_threshold;

        return $override !== null && (int) $override >= 0
            ? (int) $override
            : self::tenantThreshold();
    }

    public static function quantityFor(Product $product): int
    {
        $result = InventoryMovement::query()
            ->where('product_id', $product->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'out' THEN -quantity ELSE quantity END), 0) AS stock_quantity")
            ->first();

        return (int) ($result->stock_quantity ?? 0);
    }

    /** @return Collection<int, Product> */
    public static function lowStockProducts(): Collection
    {
        return Product::query()
            ->where('products.active', true)
            ->leftJoin('inventory_movements as im', 'im.product_id', '=', 'products.id')
            ->select('products.*')
            ->selectRaw("COALESCE(SUM(CASE WHEN im.type = 'out' THEN -im.quantity ELSE im.quantity END), 0) AS stock_quantity")
            ->groupBy('products.id')
            ->havingRaw('stock_quantity < COALESCE(products.low_stock_threshold, ?)', [self::tenantThreshold()])
            ->get();
    }

    public static function lowStockCount(): int
    {
        return self::lowStockProducts()->count();
    }
}
