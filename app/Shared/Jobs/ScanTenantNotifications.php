<?php

declare(strict_types=1);

namespace App\Shared\Jobs;

use App\Models\User;
use App\Shared\Constants\NotificationTypes;
use App\Shared\Contracts\NotificationDispatcherInterface;
use App\Shared\Support\StockLevels;

/**
 * Per-tenant sweep for notification conditions that are not event-driven.
 *
 * Covers passwordless accounts (existing users never trigger a login event
 * again) and stock that became low because a threshold was lowered. Both use
 * the dispatcher's dedupe keys, so re-running the job is idempotent.
 */
class ScanTenantNotifications extends TenantAwareJob
{
    protected function execute(): void
    {
        $dispatcher = app(NotificationDispatcherInterface::class);

        User::query()
            ->whereNull('password')
            ->lazyById(200)
            ->each(function (User $user) use ($dispatcher) {
                $dispatcher->dispatch(
                    NotificationTypes::PASSWORD_NOT_SET,
                    ['body' => 'Add a password so you can sign in without Google.'],
                    $user->id,
                    'password:'.$user->id,
                );
            });

        StockLevels::lowStockProducts()
            ->each(function ($product) use ($dispatcher) {
                $quantity = (int) ($product->stock_quantity ?? StockLevels::quantityFor($product));
                $threshold = StockLevels::effectiveThreshold($product);

                $dispatcher->dispatch(
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
            });
    }
}
