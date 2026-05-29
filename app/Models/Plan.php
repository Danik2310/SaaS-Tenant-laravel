<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'price',
        'max_users',
        'max_storage',
        'max_warehouses',
        'max_categories',
        'max_products',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'max_users' => 'integer',
        'max_storage' => 'integer',
        'max_warehouses' => 'integer',
        'max_categories' => 'integer',
        'max_products' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function featureGates(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function getFeaturesAttribute(): array
    {
        $gates = $this->relationLoaded('featureGates')
            ? $this->featureGates
            : $this->featureGates()->get();

        return $gates->where('is_enabled', true)->pluck('feature_key')->values()->toArray();
    }

    public function hasFeature(string $feature): bool
    {
        if ($this->relationLoaded('featureGates')) {
            $found = $this->featureGates->firstWhere('feature_key', $feature);

            return $found?->is_enabled ?? false;
        }

        return $this->featureGates()
            ->where('feature_key', $feature)
            ->value('is_enabled') ?? false;
    }

    public function getLimit(string $limit): int
    {
        return match ($limit) {
            'users' => $this->max_users !== null ? (int) $this->max_users : PHP_INT_MAX,
            'storage' => $this->max_storage !== null ? (int) $this->max_storage : PHP_INT_MAX,
            'warehouses' => $this->max_warehouses !== null ? (int) $this->max_warehouses : PHP_INT_MAX,
            'categories' => $this->max_categories !== null ? (int) $this->max_categories : PHP_INT_MAX,
            'products' => $this->max_products !== null ? (int) $this->max_products : PHP_INT_MAX,
            default => PHP_INT_MAX,
        };
    }
}
