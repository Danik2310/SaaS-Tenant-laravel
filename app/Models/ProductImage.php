<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'path',
        'disk',
        'size_bytes',
        'sort_order',
    ];

    /**
     * Storage internals never leave the server: the resolved URL is the only
     * image locator the frontend is allowed to see.
     */
    protected $hidden = [
        'disk',
        'path',
    ];

    protected $appends = [
        'url',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'sort_order' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Serialize the resolved URL instead of the underlying disk/path pair.
     */
    public function getUrlAttribute(): ?string
    {
        if ($this->disk === null || $this->path === null) {
            return null;
        }

        return $this->url();
    }

    public function deleteFile(): bool
    {
        return Storage::disk($this->disk)->delete($this->path);
    }
}
