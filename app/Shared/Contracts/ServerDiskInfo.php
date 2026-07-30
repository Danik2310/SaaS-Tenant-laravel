<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface ServerDiskInfo
{
    public function totalGb(): ?float;

    public function freeGb(): ?float;

    public function usedGb(): ?float;

    public function usedPct(): ?float;

    public function driver(): string;

    public function label(): string;
}
