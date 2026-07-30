<?php

declare(strict_types=1);

namespace App\Observers\Tenant;

use App\Models\ProductImage;
use App\Models\TenantResourceUsage;

class ProductImageObserver
{
    public function created(ProductImage $image): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'storage_kb', $image->size_bytes > 0 ? max(1, (int) ceil($image->size_bytes / 1024)) : 1);
    }

    public function deleted(ProductImage $image): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'storage_kb', $image->size_bytes > 0 ? -(int) ceil($image->size_bytes / 1024) : -1);

        $image->deleteFile();
    }
}
