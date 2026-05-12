<?php

declare(strict_types=1);

namespace App\Observers\Tenant;

use App\Models\Product;
use App\Models\TenantResourceUsage;

class ProductObserver
{
    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'products_count', 1);
    }

    /**
     * Handle the Product "deleted" event (including soft deletes).
     *
     * Product uses the SoftDeletes trait. When forceDelete() is called it internally
     * calls delete(), which fires the deleted() observer. If the model was already
     * soft-deleted (original deleted_at is set), the count was already decremented
     * during the soft delete — so we skip the second decrement to avoid double-counting.
     */
    public function deleted(Product $product): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        // When forceDelete() is called on an already soft-deleted model the count
        // was already decremented during the soft delete — skip to avoid double counting.
        if ($product->isForceDeleting() && $product->getOriginal('deleted_at') !== null) {
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'products_count', -1);
    }

    /**
     * Handle the Product "restored" event (after soft delete restoration).
     */
    public function restored(Product $product): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'products_count', 1);
    }

    /**
     * Handle the Product "force deleted" event.
     *
     * This fires after deleted() when forceDelete() is used. The count has already
     * been handled in deleted() (either decremented or skipped), so no action is needed.
     */
    public function forceDeleted(Product $product): void
    {
        // Count was already handled in the deleted() observer.
        // If this was a force delete after a soft delete, deleted() skipped it.
        // If this was a direct force delete, deleted() decremented the count.
    }
}
