<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $connection = 'mysql_central';

    use HasFactory;

    protected $fillable = [
        'name',
        'provider',
        'api_key',
        'secret_key',
        'mode',
        'active',
    ];

    protected $hidden = [
        'api_key',
        'secret_key',
    ];

    protected $casts = [
        'active' => 'boolean',
        'api_key' => 'encrypted',
        'secret_key' => 'encrypted',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
