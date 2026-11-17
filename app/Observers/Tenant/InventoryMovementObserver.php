<?php

declare(strict_types=1);

namespace App\Observers\Tenant;

use App\Models\InventoryMovement;
use App\Shared\Constants\NotificationTypes;
use App\Shared\Contracts\NotificationDispatcherInterface;
use App\Shared\Support\StockLevels;

/**
 * Turns a stock movement into a live low-stock alert.
 *
 * No-ops outside tenant context, like every other tenant observer. The dedupe
 * key is the product id, so repeated movements on an already-low product do not
 * flood the notification centre.
 */
class InventoryMovementObserver
{
    public function created(InventoryMovement $movement): void
    {
        if (tenant('id') === null) {
            return;
        }

        $product = $movement->product;

        if ($product === null || ! $product->active) {
            return;
        }

        $quantity = StockLevels::quantityFor($product);
        $threshold = StockLevels::effectiveThreshold($product);

        if ($quantity > $threshold) {
            return;
        }

        app(NotificationDispatcherInterface::class)->dispatch(
            NotificationTypes::LOW_STOCK,
            [
                'body' => sprintf('%s has %d units left (alert at %d).', $product->name, $quantity, $threshold),
                'data' => [
                    'subject_id' => $product->id,
                    'quantity' => $quantity,
                    'threshold' => $threshold,
                    'action_url' => route('tenant.products.edit', $product),
                ],
            ],
            null,
            'product:'.$product->id,
        );
    }
}
