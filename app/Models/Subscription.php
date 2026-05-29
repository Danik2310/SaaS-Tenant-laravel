<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    public static function createForTenant(Tenant $tenant, ?Plan $plan, string $status = 'active', ?\DateTimeInterface $endsAt = null): self
    {
        if (! $plan) {
            throw new \InvalidArgumentException('Cannot create subscription without a plan. Tenant: '.$tenant->id);
        }

        return static::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => $endsAt,
            'status' => $status,
        ]);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->where('ends_at', '<', now());
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
