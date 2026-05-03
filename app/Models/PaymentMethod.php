<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PaymentMethod extends Model
{
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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Accessor para desencriptar api_key
    public function getApiKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    // Mutator para encriptar api_key
    public function setApiKeyAttribute($value)
    {
        $this->attributes['api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    // Accessor para desencriptar secret_key
    public function getSecretKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    // Mutator para encriptar secret_key
    public function setSecretKeyAttribute($value)
    {
        $this->attributes['secret_key'] = $value ? Crypt::encryptString($value) : null;
    }
}
