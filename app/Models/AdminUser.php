<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

class AdminUser extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $table = 'admin_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    /**
     * Scope para usuarios activos.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para usuarios inactivos.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Obtener todos los roles del usuario con sus permisos.
     */
    public function getRolesWithPermissions()
    {
        return $this->roles()->with('permissions')->get();
    }

    /**
     * Obtener los nombres de los roles del usuario.
     */
    public function getRoleNames()
    {
        return $this->roles->pluck('name')->toArray();
    }

    /**
     * Obtener todos los permisos del usuario (directos + heredados de roles).
     */
    public function getAllPermissions()
    {
        return $this->permissions()->union(
            $this->roles()->with('permissions')->get()->flatMap->permissions
        )->unique('id')->values();
    }
}
