<?php

declare(strict_types=1);

namespace App\Tenants\Contracts;

interface DatabaseServiceInterface
{
    public function databaseExists(string $name): bool;

    public function dropDatabase(string $name): void;

    public function getDatabaseSizeKb(string $name): int;
}
