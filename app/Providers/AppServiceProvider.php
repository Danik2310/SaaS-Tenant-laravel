<?php

namespace App\Providers;

use App\Billing\Adapters\StripePaymentAdapter;
use App\Billing\Contracts\PaymentGatewayInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, StripePaymentAdapter::class);
    }

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
