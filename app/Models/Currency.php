<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $connection = 'mysql_central';

    protected $table = 'currencies';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'region',
        'popular',
        'active',
        'allow_tenant',
        'allow_public',
        'sort_order',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'popular' => 'boolean',
        'active' => 'boolean',
        'allow_tenant' => 'boolean',
        'allow_public' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
