<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AdminUser;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\GlobalSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Observers\ActivityLogObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    public function boot(): void
    {
        // Central models
        Tenant::observe(ActivityLogObserver::class);
        Plan::observe(ActivityLogObserver::class);
        AdminUser::observe(ActivityLogObserver::class);
        PaymentMethod::observe(ActivityLogObserver::class);
        Subscription::observe(ActivityLogObserver::class);
        Role::observe(ActivityLogObserver::class);
        Permission::observe(ActivityLogObserver::class);
        Domain::observe(ActivityLogObserver::class);
        GlobalSetting::observe(ActivityLogObserver::class);
        Setting::observe(ActivityLogObserver::class);

        // Tenant models
        User::observe(ActivityLogObserver::class);
        Customer::observe(ActivityLogObserver::class);
        Product::observe(ActivityLogObserver::class);
        Order::observe(ActivityLogObserver::class);
        Payment::observe(ActivityLogObserver::class);
        Category::observe(ActivityLogObserver::class);
        Warehouse::observe(ActivityLogObserver::class);
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
