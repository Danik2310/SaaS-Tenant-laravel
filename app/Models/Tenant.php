<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\DatabaseConfig;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'id',
        'name',
        'email',
        'domain',
        'status',
        'plan_id',
        'trial_ends_at',
        'tenancy_db_name',
        'reference_id',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $tenant) {
            if (empty($tenant->reference_id)) {
                $tenant->reference_id = self::generateReferenceId();
            }
        });
    }

    public static function generateReferenceId(): string
    {
        $date = now()->format('Ymd');
        $lock = Cache::lock("ref_id_counter:{$date}", 10);

        $lock->block(5);

        try {
            $counter = Cache::get("ref_id_counter:{$date}", 0) + 1;
            Cache::put("ref_id_counter:{$date}", $counter, now()->addDays(2));

            return 'TEN-'.$date.'-'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT);
        } finally {
            $lock->release();
        }
    }

    protected function encodeAttributes(): void
    {
        // No-op: we use real database columns, not a virtual data column
    }

    protected function decodeVirtualColumn(): void
    {
        // No-op: we use real database columns, not a virtual data column
    }

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'email',
            'domain',
            'status',
            'plan_id',
            'trial_ends_at',
            'tenancy_db_name',
            'reference_id',
            'deleted_at',
            'created_at',
            'updated_at',
        ];
    }

    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    public function database(): DatabaseConfig
    {
        return new DatabaseConfig($this);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->where('status', 'active');
    }

    public function hasFeature(string $feature): bool
    {
        return $this->plan?->hasFeature($feature) ?? false;
    }

    public function getLimit(string $limit): int
    {
        return $this->plan?->getLimit($limit) ?? 0;
    }

    public function isOnTrial(): bool
    {
        return $this->status === 'Trial'
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    public function trialHasExpired(): bool
    {
        return $this->status === 'Trial'
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isPast();
    }
}
