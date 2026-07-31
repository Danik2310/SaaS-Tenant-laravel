<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $connection = 'mysql_central';

    protected $fillable = [
        'key',
        'label',
        'description',
        'is_locked',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
