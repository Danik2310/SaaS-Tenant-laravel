<?php

namespace App\Models;

use App\Shared\Traits\EnforcesActivePermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class AdminUser extends Authenticatable
{
    use EnforcesActivePermissions, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    // NOTE: $connection intentionally NOT set to 'mysql_central'. Spatie's HasRoles trait
    // resolves the database connection from the model, and permission tables are migrated
    // on the default (mysql) connection. Using a different connection causes InnoDB lock
    // conflicts when Spatie operations (assignRole, givePermissionTo) create pivot records
    // across connections. AdminUser is only accessed in central-domain context where
    // DatabaseTenancyBootstrapper does not switch the connection, so the default is safe.
    // If tenancy initialization becomes possible during admin requests, Spatie permission
    // operations must first be migrated to also use 'mysql_central'.

    protected $table = 'admin_users';

    protected $guard_name = 'admin';

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
        $this->loadMissing('roles.permissions', 'permissions');

        return $this->permissions
            ->merge($this->roles->flatMap->permissions)
            ->unique('id')
            ->values();
    }
}
