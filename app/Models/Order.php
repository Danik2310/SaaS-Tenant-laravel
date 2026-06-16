<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'user_id',
        'status',
        'total',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the customer this order belongs to.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get all items in this order.
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get all payments for this order.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get total amount paid.
     */
    public function totalPaid()
    {
        return $this->payments()->sum('amount');
    }

    /**
     * Get remaining balance.
     */
    public function balanceDue()
    {
        return $this->total - $this->totalPaid();
    }

    /**
     * Check if order is fully paid.
     */
    public function isPaid()
    {
        return $this->balanceDue() <= 0;
    }
}
