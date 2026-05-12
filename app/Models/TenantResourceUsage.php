<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantResourceUsage extends Model
{
    protected $table = 'tenant_resource_usage';

    protected $fillable = [
        'tenant_id',
        'users_count',
        'storage_kb',
        'db_size_kb',
        'products_count',
        'orders_count',
        'collected_at',
    ];

    protected $casts = [
        'users_count' => 'integer',
        'storage_kb' => 'integer',
        'db_size_kb' => 'integer',
        'products_count' => 'integer',
        'orders_count' => 'integer',
        'collected_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
