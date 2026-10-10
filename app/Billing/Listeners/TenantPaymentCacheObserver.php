<?php

declare(strict_types=1);

namespace App\Billing\Listeners;

use App\Models\SubscriptionPayment;
use Illuminate\Support\Facades\Cache;

/**
 * Flushes a tenant's cached payment history whenever one of their payment
 * records changes.
 *
 * The flush is keyed by the payment's tenant_id directly and never initializes
 * tenancy. If the tenant's database has been dropped, DatabaseTenancyBootstrapper
 * would throw and leave the cache in a corrupt half-initialized state — so we
 * stay purely on the central columns and cache tags.
 */
class TenantPaymentCacheObserver
{
    public function saved(SubscriptionPayment $payment): void
    {
        $this->flushTenantHistory($payment);
    }

    public function deleted(SubscriptionPayment $payment): void
    {
        $this->flushTenantHistory($payment);
    }

    private function flushTenantHistory(SubscriptionPayment $payment): void
    {
        Cache::tags([
            config('tenancy.cache.tag_base').$payment->tenant_id,
            'billing',
        ])->flush();
    }
}
