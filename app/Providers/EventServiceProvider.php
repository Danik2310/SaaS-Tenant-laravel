<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AdminUser;
use App\Models\Category;
use App\Models\Customer;
use App\Models\GlobalSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Observers\Tenant\CategoryObserver;
use App\Observers\Tenant\OrderObserver;
use App\Observers\Tenant\ProductImageObserver;
use App\Observers\Tenant\ProductObserver;
use App\Observers\Tenant\UserObserver;
use App\Observers\Tenant\WarehouseObserver;
use App\Shared\Observers\ActivityLogObserver;
use App\Shared\Support\ImpersonatedAdmin;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\CauserResolver;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Central event and model-observer registration.
 *
 * Flow:
 *  1. `$listen` binds the framework's `Registered` event to the email
 *     verification listener.
 *  2. `boot()` attaches observers:
 *     - every central model and tenant model → {@see ActivityLogObserver}, which
 *       is tenant-context aware and records create/update/delete to the activity
 *       log.
 *     - tenant resource models → the `App\Observers\Tenant\*` observers that keep
 *       incremental resource-usage counters in sync.
 *
 * Tenancy lifecycle events (provisioning, suspend, reactivate) are wired
 * separately in {@see TenancyServiceProvider}.
 *
 * @see docs/architecture/07-jobs-events-observers.md
 */
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
        // Tenant models — observers guard themselves with tenancy checks
        User::observe(ActivityLogObserver::class);
        Customer::observe(ActivityLogObserver::class);
        Product::observe(ActivityLogObserver::class);
        Order::observe(ActivityLogObserver::class);
        Payment::observe(ActivityLogObserver::class);
        Category::observe(ActivityLogObserver::class);
        Warehouse::observe(ActivityLogObserver::class);
        Setting::observe(ActivityLogObserver::class);

        // Tenant resource usage observers (event-driven incremental counting)
        User::observe(UserObserver::class);
        Product::observe(ProductObserver::class);
        Order::observe(OrderObserver::class);
        ProductImage::observe(ProductImageObserver::class);
        Category::observe(CategoryObserver::class);
        Warehouse::observe(WarehouseObserver::class);

        $this->registerActivityCauserResolver();
    }

    /**
     * God-mode (impersonation) installs a non-persisted {@see ImpersonatedAdmin}
     * into the `web` guard. The activity log's default causer resolver expects
     * an Eloquent model and its `resolve()` signature is typed `Model|int|string|null`,
     * so any `activity()` call made while impersonating would throw a TypeError.
     * Fall back to `null` (no causer) whenever the authenticated user is not a
     * persisted model; real model causers are passed through untouched.
     */
    private function registerActivityCauserResolver(): void
    {
        app(CauserResolver::class)->resolveUsing(function ($subject) {
            if ($subject instanceof Model) {
                return $subject;
            }

            if ($subject === null) {
                $user = Auth::user();

                return $user instanceof Model ? $user : null;
            }

            return null;
        });
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
