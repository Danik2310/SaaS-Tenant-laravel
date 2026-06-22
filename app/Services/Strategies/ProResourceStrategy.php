<?php

declare(strict_types=1);

namespace App\Services\Strategies;

use App\Contracts\ResourceEnforcementInterface;
use App\Models\Plan;

class ProResourceStrategy implements ResourceEnforcementInterface
{
    public function __construct(
        private readonly Plan $plan,
    ) {}

    public function maxUsers(): int
    {
        return $this->plan->max_users ?? 50;
    }

    public function maxStorageMb(): int
    {
        return $this->plan->max_storage ?? 1024;
    }

    public function maxWarehouses(): int
    {
        return $this->plan->max_warehouses ?? 5;
    }

    public function maxCategories(): int
    {
        return $this->plan->max_categories ?? 50;
    }

    public function maxProducts(): int
    {
        return $this->plan->max_products ?? 500;
    }

    public function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'pdf'];
    }

    public function hasFeature(string $feature): bool
    {
        return $this->plan->hasFeature($feature);
    }
}
