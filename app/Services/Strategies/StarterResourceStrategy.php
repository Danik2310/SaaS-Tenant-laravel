<?php

declare(strict_types=1);

namespace App\Services\Strategies;

use App\Contracts\ResourceEnforcementInterface;
use App\Models\Plan;

class StarterResourceStrategy implements ResourceEnforcementInterface
{
    public function __construct(
        private readonly Plan $plan,
    ) {}

    public function maxUsers(): int
    {
        return $this->plan->max_users ?? 5;
    }

    public function maxStorageMb(): int
    {
        return $this->plan->max_storage ?? 100;
    }

    public function maxWarehouses(): int
    {
        return $this->plan->max_warehouses ?? 1;
    }

    public function maxCategories(): int
    {
        return $this->plan->max_categories ?? 10;
    }

    public function maxProducts(): int
    {
        return $this->plan->max_products ?? 50;
    }

    public function allowedExportFormats(): array
    {
        return ['csv'];
    }

    public function hasFeature(string $feature): bool
    {
        return $this->plan->hasFeature($feature);
    }
}
