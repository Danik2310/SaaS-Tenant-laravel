<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Durable idempotency ledger for Stripe webhook deliveries.
 *
 * Stripe retries deliveries, so a card-change notification must be sent once
 * per event id. This table lives on the central connection because webhooks
 * arrive on a central domain, outside any tenant context.
 */
class StripeWebhookEvent extends Model
{
    protected $connection = 'mysql_central';

    protected $table = 'stripe_webhook_events';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'type',
    ];
}
