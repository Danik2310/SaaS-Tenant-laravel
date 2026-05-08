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
            'users' => (int) ($this->max_users ?? 0),
            'storage' => (int) ($this->max_storage ?? 0),
            default => (int) (is_array($this->features) && isset($this->features[$limit]) ? $this->features[$limit] : 0),
        };
    }
}
