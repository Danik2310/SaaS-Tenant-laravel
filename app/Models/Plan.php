<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'price',
        'max_users',
        'max_storage',
        'features',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'max_users' => 'integer',
        'features' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function hasFeature(string $feature): bool
    {
        $features = $this->features ?? [];

        return in_array($feature, $features, true) || ($features[$feature] ?? false) === true;
    }

    public function getLimit(string $limit): int
    {
        return match ($limit) {
            'users' => $this->max_users !== null ? (int) $this->max_users : PHP_INT_MAX,
            'storage' => $this->max_storage !== null ? (int) $this->max_storage : PHP_INT_MAX,
            default => (int) (is_array($this->features) && isset($this->features[$limit]) ? $this->features[$limit] : 0),
        };
    }
}
