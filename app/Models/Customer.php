<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'document',
        'notes',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get all orders for this customer.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get active orders only.
     */
    public function activeOrders()
    {
        return $this->orders()->where('status', '!=', 'cancelled');
    }

    /**
     * Get total spent by this customer.
     */
    public function totalSpent()
    {
        return $this->orders()
            ->where('status', 'paid')
            ->sum('total');
    }
}
