<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'sku',
        'category_id',
        'price',
        'cost',
        'active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the category this product belongs to.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get all order items for this product.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get inventory movements for this product.
     */
    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Calculate margin from price and cost.
     */
    public function getMarginAttribute()
    {
        if ($this->cost == 0) {
            return 0;
        }
        return (($this->price - $this->cost) / $this->cost) * 100;
    }

    /**
     * Get total quantity sold.
     */
    public function totalSold()
    {
        return $this->orderItems()->sum('quantity');
    }

    /**
     * Get total revenue from this product.
     */
    public function totalRevenue()
    {
        return $this->orderItems()->sum('subtotal');
    }
}
