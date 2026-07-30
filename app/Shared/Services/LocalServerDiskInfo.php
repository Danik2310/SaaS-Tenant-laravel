<?php

declare(strict_types=1);

namespace App\Shared\Services;

use App\Shared\Contracts\ServerDiskInfo;

class LocalServerDiskInfo implements ServerDiskInfo
{
    private string $path;

    public function __construct()
    {
        $path = config('filesystems.disks.tenant.root');

        if ($path === null || !is_dir($path)) {
            $path = storage_path();
        }

        $this->path = $path;
    }

    public function totalGb(): ?float
    {
        $total = @disk_total_space($this->path);

        return $total !== false ? round($total / 1073741824, 1) : null;
    }

    public function freeGb(): ?float
    {
        $free = @disk_free_space($this->path);

        return $free !== false ? round($free / 1073741824, 1) : null;
    }

    public function usedGb(): ?float
    {
        $total = $this->totalGb();
        $free = $this->freeGb();

        if ($total === null || $free === null) {
            return null;
        }

        return round($total - $free, 1);
    }

    public function usedPct(): ?float
    {
        $total = $this->totalGb();
        $free = $this->freeGb();

        if ($total === null || $free === null || $total <= 0) {
            return null;
        }

        return round((($total - $free) / $total) * 100, 1);
    }

    public function driver(): string
    {
        return 'local';
    }

    public function label(): string
    {
        $relative = str_replace(base_path(), '', $this->path);

        return 'Local (' . ltrim($relative, '/\\') . ')';
    }
}
