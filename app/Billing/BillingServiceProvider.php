<?php

declare(strict_types=1);

namespace App\Billing;

use App\Billing\Console\Commands\ExpireSubscriptionsCommand;
use App\Billing\Console\Commands\SyncTenantSubscriptions;
use App\Billing\Console\Commands\UpdatePaymentMethod;
use App\Billing\Contracts\PaymentGatewayInterface;
use App\Billing\Events\PlanChanged;
use App\Billing\Factories\PaymentGatewayFactory;
use App\Billing\Listeners\HandlePlanChange;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, function () {
            $tenant = null;

            try {
                $tenant = tenancy()->tenant;
            } catch (\Throwable) {
                // Not in tenant context
            }

            return PaymentGatewayFactory::fromTenant($tenant);
        });
    }

    public function boot(): void
    {
        Event::listen(PlanChanged::class, HandlePlanChange::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireSubscriptionsCommand::class,
                SyncTenantSubscriptions::class,
                UpdatePaymentMethod::class,
            ]);
        }
    }
}
