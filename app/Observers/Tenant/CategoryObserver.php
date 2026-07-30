<?php

declare(strict_types=1);

namespace App\Observers\Tenant;

use App\Models\Category;
use App\Models\TenantResourceUsage;

class CategoryObserver
{
    public function created(Category $category): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'categories_count', 1);
    }

    public function deleted(Category $category): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'categories_count', -1);
    }

    public function restored(Category $category): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        TenantResourceUsage::incrementCount($tenantId, 'categories_count', 1);
    }
}
