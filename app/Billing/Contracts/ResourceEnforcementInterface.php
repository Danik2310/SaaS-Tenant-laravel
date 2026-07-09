<?php

declare(strict_types=1);

namespace App\Billing\Contracts;

interface ResourceEnforcementInterface
{
    public function maxUsers(): int;

    public function maxStorageMb(): int;

    public function maxWarehouses(): int;

    public function maxCategories(): int;

    public function maxProducts(): int;

    public function allowedExportFormats(): array;

    public function hasFeature(string $feature): bool;
}
