<?php

declare(strict_types=1);

namespace App\Contracts;

interface ExportServiceInterface
{
    public function export(string $entity, string $format, array $columns, array $filters = []): array;

    public function getDownloadUrl(string $path): ?string;

    public function cleanupExpired(): void;
}
