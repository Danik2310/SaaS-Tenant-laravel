<?php

declare(strict_types=1);

namespace App\Tenants\Listeners;

use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Events\TenantCreated;

class CreateTenantStorageSkeleton
{
    public function handle(TenantCreated $event): void
    {
        $key = $event->tenant->getTenantKey();
        $base = storage_path(config('tenancy.filesystem.suffix_base').$key);

        foreach ([
            $base,
            $base.'/app',
            $base.'/app/public',
        ] as $directory) {
            if (is_dir($directory)) {
                continue;
            }

            try {
                mkdir($directory, 0775, true);
            } catch (\Throwable $e) {
                Log::warning('Could not create tenant storage skeleton at '.$directory.': '.$e->getMessage());
            }
        }
    }
}
