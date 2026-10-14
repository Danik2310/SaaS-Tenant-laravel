<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * App-wide defaults applied at boot.
 *
 * Flow:
 *  1. Pin the default string length to 191 chars (legacy MySQL index limit).
 *  2. In non-production, fail loudly on N+1 lazy loads
 *     (`Model::preventLazyLoading`).
 *  3. In production, downgrade those violations to a warning log instead of an
 *     exception, so one missed eager-load cannot take the app down.
 */
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Model::preventLazyLoading(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
                \Log::warning("Lazy loading {$relation} on ".get_class($model));
            });
        }
    }
}
