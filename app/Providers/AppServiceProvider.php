<?php

namespace App\Providers;

use App\Contracts\TenantManagerInterface;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantManagerInterface::class, TenantManager::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(!$this->app->isProduction());
    }
}
