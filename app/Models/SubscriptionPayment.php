<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    protected $connection = 'mysql_central';

    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'tenant_id',
        'amount',
        'method',
        'reference',
        'status',
        'paid_at',
        'notes',
        'plan_name',
        'plan_price',
        'currency',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'plan_price' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SubscriptionPayment $payment) {
            $payment->fillPlanSnapshot();
        });
    }

    /**
     * Snapshot the plan the subscription was billed under so the tenant-facing
     * history renders plan / price / currency without joins, and the values
     * stay frozen even if the tenant switches plans afterwards.
     */
    public function fillPlanSnapshot(): void
    {
        if (filled($this->plan_name) || ! $this->subscription_id) {
            return;
        }

        $subscription = Subscription::query()->with('plan')->find($this->subscription_id);

        if (! $subscription?->plan) {
            return;
        }

        $this->plan_name = $subscription->plan->name;
        $this->plan_price ??= $subscription->plan->price;
        $this->currency ??= strtoupper((string) GlobalSetting::get('currency', 'USD'));
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
