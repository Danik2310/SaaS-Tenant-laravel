<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
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
        'status',
        'plan_id',
        'trial_ends_at',
        'tenancy_db_name',
        'reference_id',
        'company_name',
        'first_name',
        'last_name',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'owner_email_verified_at' => 'datetime',
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
            $counter = Cache::get("ref_id_counter:{$date}");

            if ($counter === null) {
                $prefix = 'TEN-'.$date.'-';

                $max = static::withTrashed()
                    ->where('reference_id', 'like', $prefix.'%')
                    ->pluck('reference_id')
                    ->map(fn (string $id) => (int) substr($id, strlen($prefix)))
                    ->max() ?? 0;

                $counter = $max + 1;
            } else {
                $counter = $counter + 1;
            }

            // Guard against collisions with rows created outside this counter
            // (e.g. seeds or tests that write faker-generated reference ids).
            // Keep bumping until the candidate is free.
            $candidate = 'TEN-'.$date.'-'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT);
            while (static::withTrashed()->where('reference_id', $candidate)->exists()) {
                $counter++;
                $candidate = 'TEN-'.$date.'-'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT);
            }

            Cache::put("ref_id_counter:{$date}", $counter, now()->addDays(2));

            return $candidate;
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
            'owner_email_verified_at',
            'status',
            'plan_id',
            'trial_ends_at',
            'tenancy_db_name',
            'reference_id',
            'company_name',
            'first_name',
            'last_name',
            'phone',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country',
            'deleted_at',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Record that the tenant owner confirmed their email address.
     *
     * This is the central projection consumed by the pending registration
     * screen and the broadcast. The tenant-side `users.email_verified_at` row
     * remains authoritative; the listener writes this after it flips.
     */
    public function markOwnerEmailVerified(?\DateTimeInterface $verifiedAt = null): bool
    {
        // Deliberately not mass assignable: only the verification listener may
        // write this column, so a crafted tenant payload cannot fake it.
        //
        // Quiet save: this is a projection, and the central activity-log
        // observer stores the tenant key in a bigint `subject_id`. Test tenants
        // use string keys, and a logging failure must never undo a real
        // verification. The tenant-side user update is logged normally.
        return $this->forceFill([
            'owner_email_verified_at' => $verifiedAt ?? now(),
        ])->saveQuietly();
    }

    public function hasVerifiedOwnerEmail(): bool
    {
        return $this->owner_email_verified_at !== null;
    }

    public function domains()
    {
        return $this->hasMany(Domain::class, 'tenant_id', 'id');
    }

    public function getDomainAttribute($value)
    {
        if (! empty($value)) {
            return $value;
        }

        $primary = $this->domains()
            ->where('is_primary', true)
            ->value('domain');

        if ($primary) {
            return $primary;
        }

        return $this->domains()->latest('id')->value('domain');
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
        return $this->plan?->getLimit($limit) ?? PHP_INT_MAX;
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
