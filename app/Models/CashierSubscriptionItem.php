<?php

declare(strict_types=1);

namespace App\Models;

use Laravel\Cashier\SubscriptionItem as CashierSubscriptionItemBase;

class CashierSubscriptionItem extends CashierSubscriptionItemBase
{
    protected $connection = 'mysql_central';

    protected $table = 'cashier_subscription_items';
}
