<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Subscription extends Model
{
    protected $connection = 'mysql_central';

    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public static function createForTenant(Tenant $tenant, ?Plan $plan, string $status = 'active', ?\DateTimeInterface $endsAt = null, ?\DateTimeInterface $startsAt = null): self
    {
        if (! $plan) {
            throw new \InvalidArgumentException('Cannot create subscription without a plan. Tenant: '.$tenant->id);
        }

        $subscription = new static;
        $subscription->tenant_id = $tenant->id;
        $subscription->plan_id = $plan->id;
        $subscription->starts_at = $startsAt ?? now();
        $subscription->ends_at = $endsAt ?? self::resolveEndsAt($plan, $subscription->starts_at);
        $subscription->status = $status;
        $subscription->save();

        return $subscription;
    }

    protected static function resolveEndsAt(Plan $plan, \DateTimeInterface $startsAt): ?Carbon
    {
        if ($plan->isTrial() || $plan->duration_months === null) {
            return null;
        }

        return Carbon::parse($startsAt)->addMonths($plan->duration_months);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments()
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            });
    }

    public function scopeExpired($query)
    {
        return $query->whereIn('status', ['active', 'expired'])
            ->where('ends_at', '<', now());
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOnTrial($query)
    {
        return $query->where('status', 'trial')
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            });
    }

    public function isOnTrial(): bool
    {
        return $this->status === 'trial'
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && ($this->ends_at === null || $this->ends_at > now());
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || ($this->status === 'active' && $this->ends_at !== null && $this->ends_at < now());
    }
}
