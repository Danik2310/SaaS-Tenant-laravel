<?php

declare(strict_types=1);

namespace App\Billing\Strategies;

use App\Billing\Contracts\ResourceEnforcementInterface;
use App\Models\Plan;

class EnterpriseResourceStrategy implements ResourceEnforcementInterface
{
    public function __construct(
        private readonly Plan $plan,
    ) {}

    public function maxUsers(): int
    {
        return $this->plan->max_users ?? PHP_INT_MAX;
    }

    public function maxStorageMb(): int
    {
        return $this->plan->max_storage ?? PHP_INT_MAX;
    }

    public function maxWarehouses(): int
    {
        return $this->plan->max_warehouses ?? PHP_INT_MAX;
    }

    public function maxCategories(): int
    {
        return $this->plan->max_categories ?? PHP_INT_MAX;
    }

    public function maxProducts(): int
    {
        return $this->plan->max_products ?? PHP_INT_MAX;
    }

    public function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'pdf', 'json'];
    }

    public function hasFeature(string $feature): bool
    {
        return $this->plan->hasFeature($feature);
    }
}
