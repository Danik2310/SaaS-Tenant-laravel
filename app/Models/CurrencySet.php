<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencySet extends Model
{
    protected $connection = 'mysql_central';

    protected $table = 'currency_sets';

    protected $fillable = [
        'name',
        'slug',
        'currency_codes',
        'is_system',
    ];

    protected $casts = [
        'currency_codes' => 'array',
        'is_system' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
