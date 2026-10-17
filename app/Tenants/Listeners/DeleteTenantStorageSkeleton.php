<?php

declare(strict_types=1);

namespace App\Tenants\Listeners;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Events\TenantDeleted;

/**
 * Removes the tenant's storage directories on force-delete, mirroring
 * {@see CreateTenantStorageSkeleton}.
 *
 * Soft-deletes are ignored: like the database, a soft-deleted tenant's files
 * are retained so the tenant can be restored without data loss.
 */
class DeleteTenantStorageSkeleton
{
    public function handle(TenantDeleted $event): void
    {
        if (! $event->tenant->isForceDeleting()) {
            return;
        }

        $key = $event->tenant->getTenantKey();
        $base = storage_path(config('tenancy.filesystem.suffix_base').$key);

        if (! is_dir($base)) {
            return;
        }

        try {
            File::deleteDirectory($base);
        } catch (\Throwable $e) {
            Log::warning('Could not delete tenant storage skeleton at '.$base.': '.$e->getMessage());
        }
    }
}
