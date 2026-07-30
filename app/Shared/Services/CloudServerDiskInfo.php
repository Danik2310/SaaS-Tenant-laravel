<?php

declare(strict_types=1);

namespace App\Shared\Services;

use App\Shared\Contracts\ServerDiskInfo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CloudServerDiskInfo implements ServerDiskInfo
{
    private const CACHE_KEY = 'server_disk_cloud_usage';
    private const CACHE_TTL = 900;

    private ?float $limitGb;

    public function __construct()
    {
        $limit = env('CLOUD_STORAGE_LIMIT_GB');

        $this->limitGb = $limit !== null && $limit !== '' ? (float) $limit : null;
    }

    public function totalGb(): ?float
    {
        return $this->limitGb;
    }

    public function freeGb(): ?float
    {
        $total = $this->totalGb();
        $used = $this->usedGb();

        if ($total === null || $used === null) {
            return null;
        }

        return round($total - $used, 1);
    }

    public function usedGb(): ?float
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            try {
                $disk = Storage::disk('s3');
                $client = $disk->getClient();
                $bucket = $disk->getConfig('bucket');

                if (empty($bucket)) {
                    return 0.0;
                }

                $totalBytes = 0;
                $continuationToken = null;

                do {
                    $args = [
                        'Bucket' => $bucket,
                        'MaxKeys' => 1000,
                    ];

                    if ($continuationToken !== null) {
                        $args['ContinuationToken'] = $continuationToken;
                    }

                    $result = $client->listObjectsV2($args);

                    if (isset($result['Contents'])) {
                        foreach ($result['Contents'] as $object) {
                            $totalBytes += $object['Size'] ?? 0;
                        }
                    }

                    $continuationToken = $result['NextContinuationToken'] ?? null;
                } while ($continuationToken !== null);

                return round($totalBytes / 1073741824, 1);
            } catch (\Throwable) {
                return 0.0;
            }
        });
    }

    public function usedPct(): ?float
    {
        $total = $this->totalGb();
        $used = $this->usedGb();

        if ($total === null || $total <= 0) {
            return null;
        }

        return round(($used / $total) * 100, 1);
    }

    public function driver(): string
    {
        return 's3';
    }

    public function label(): string
    {
        $bucket = config('filesystems.disks.s3.bucket', 'unknown');

        return 'AWS S3 (' . $bucket . ')';
    }
}
