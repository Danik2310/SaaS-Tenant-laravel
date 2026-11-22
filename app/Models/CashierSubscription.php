<?php

declare(strict_types=1);

namespace App\Models;

use Laravel\Cashier\Subscription as CashierSubscriptionBase;

class CashierSubscription extends CashierSubscriptionBase
{
    protected $connection = 'mysql_central';

    protected $table = 'cashier_subscriptions';

    /**
     * Cashier derives this from the class name, which would be
     * `cashier_subscription_id`. Our central table uses the conventional
     * `subscription_id` for its items relation.
     */
    public function getForeignKey(): string
    {
        return 'subscription_id';
    }
}
