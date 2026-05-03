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
        'features' => 'array', // Assuming features is JSON array
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
