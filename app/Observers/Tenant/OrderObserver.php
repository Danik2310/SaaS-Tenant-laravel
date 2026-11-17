<?php

declare(strict_types=1);

namespace App\Observers\Tenant;

use App\Models\Order;
use App\Models\Setting;
use App\Models\TenantResourceUsage;
use App\Shared\Constants\NotificationTypes;
use App\Shared\Contracts\NotificationDispatcherInterface;
use App\Shared\Support\NotificationSettings;

/** Keeps `orders_count` in sync on create/delete/restore. */
class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'orders_count', 1);

        $this->notifyIfLarge($order);
    }

    /**
     * Alert the users who manage orders when an order is unusual.
     *
     * Two independent rules: an absolute tenant-configured threshold, or a
     * multiple of the trailing 30-day average. Backfilled orders (older than
     * five minutes) are skipped so seeding/importing history cannot produce a
     * burst of alerts.
     */
    private function notifyIfLarge(Order $order): void
    {
        if ($order->created_at?->lt(now()->subMinutes(5))) {
            return;
        }

        $threshold = $this->setting(NotificationSettings::LARGE_ORDER_THRESHOLD);
        $multiplier = $this->setting(NotificationSettings::SPIKE_MULTIPLIER) ?: 3;

        $average = (float) Order::query()
            ->whereKeyNot($order->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->avg('total');

        $total = (float) $order->total;
        $isLarge = ($threshold > 0 && $total >= $threshold)
            || ($average > 0 && $total >= $average * $multiplier);

        if (! $isLarge) {
            return;
        }

        app(NotificationDispatcherInterface::class)->dispatch(
            NotificationTypes::LARGE_ORDER,
            [
                'body' => sprintf('Order #%d for %s was flagged as unusually large.', $order->id, number_format($total, 2)),
                'data' => [
                    'subject_id' => $order->id,
                    'total' => $total,
                    'threshold' => $threshold,
                    'action_url' => route('tenant.dashboard'),
                ],
            ],
            null,
            'order:'.$order->id,
        );
    }

    private function setting(string $key): float
    {
        $value = Setting::query()->where('key', $key)->first()?->value;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'orders_count', -1);
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'orders_count', 1);
    }
}
