<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TenantReactivated;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HandleTenantReactivation
{
    public function handle(TenantReactivated $event): void
    {
        try {
            Cache::tags([config('tenancy.cache.tag_base').$event->tenant->getTenantKey()])->flush();
        } catch (\BadMethodCallException) {
            // Cache driver does not support tags (array/file)
        } catch (\Throwable $e) {
            Log::warning('Could not flush cache for tenant '.$event->tenant->id.' after reactivation: '.$e->getMessage());
        }
    }
}
