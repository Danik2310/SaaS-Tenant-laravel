<?php

declare(strict_types=1);

namespace App\Billing;

use App\Billing\Console\Commands\ExpireSubscriptionsCommand;
use App\Billing\Console\Commands\SyncTenantSubscriptions;
use App\Billing\Console\Commands\UpdatePaymentMethod;
use App\Billing\Events\PlanChanged;
use App\Billing\Factories\PaymentGatewayFactory;
use App\Billing\Listeners\HandlePlanChange;
use App\Billing\Listeners\TenantPaymentCacheObserver;
use App\Billing\Projectors\LocalSubscriptionProjector;
use App\Billing\Resolvers\StripePricePlanResolver;
use App\Models\BillingCustomer;
use App\Models\CashierSubscription;
use App\Models\CashierSubscriptionItem;
use App\Models\SubscriptionPayment;
use App\Shared\Contracts\PaymentGatewayInterface;
use App\Shared\Contracts\StripePricePlanResolverInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

/**
 * Billing module provider.
 *
 * Flow:
 *  1. `register()` wires Cashier to the tenant billable view and central
 *     subscription models, and binds {@see PaymentGatewayInterface} to a gateway
 *     resolved at build time by {@see PaymentGatewayFactory::fromTenant()} using
 *     the current tenant's configuration (falling back safely when there is no
 *     tenant context).
 *  2. `boot()` listens for {@see PlanChanged} (→ {@see HandlePlanChange}),
 *     attaches the payment-cache observer, and registers the module's console
 *     commands.
 *
 * @see docs/architecture/02-providers-and-container.md
 */
class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Cashier::useCustomerModel(BillingCustomer::class);
        Cashier::useSubscriptionModel(CashierSubscription::class);
        Cashier::useSubscriptionItemModel(CashierSubscriptionItem::class);
        Cashier::ignoreRoutes();

        $this->app->bind(PaymentGatewayInterface::class, function () {
            $tenant = null;

            try {
                $tenant = tenancy()->tenant;
            } catch (\Throwable) {
                // Not in tenant context
            }

            return PaymentGatewayFactory::fromTenant($tenant);
        });

        $this->app->singleton(StripePricePlanResolverInterface::class, StripePricePlanResolver::class);
        $this->app->singleton(LocalSubscriptionProjector::class);
    }

    public function boot(): void
    {
        Event::listen(PlanChanged::class, HandlePlanChange::class);

        SubscriptionPayment::observe(TenantPaymentCacheObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireSubscriptionsCommand::class,
                SyncTenantSubscriptions::class,
                UpdatePaymentMethod::class,
            ]);
        }
    }
}
