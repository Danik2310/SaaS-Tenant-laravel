<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResourceUsageHistory extends Model
{
    protected $connection = 'mysql_central';

    protected $table = 'resource_usage_history';

    protected $fillable = [
        'tenant_id',
        'snapshot_date',
        'users_count',
        'storage_kb',
        'db_size_kb',
        'products_count',
        'orders_count',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'users_count' => 'integer',
        'storage_kb' => 'integer',
        'db_size_kb' => 'integer',
        'products_count' => 'integer',
        'orders_count' => 'integer',
    ];

    public $timestamps = false;
}
