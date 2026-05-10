<?php

namespace App\Models;

use App\Models\Plan;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\DatabaseConfig;

/**
 * Trait vacío para reemplazar HasDataColumn cuando no queremos el campo data
 */
trait NoDataColumn
{
    // Trait vacío - no hace nada
}

class Tenant extends BaseTenant implements TenantWithDatabase
{
    // Reemplazar HasDataColumn con nuestro trait vacío
    use NoDataColumn, SoftDeletes;
    protected $fillable = [
        'id',
        'name',
        'email',
        'domain',
        'status',
        'plan_id',
        'trial_ends_at',
        'tenancy_db_name',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Campos que deben guardarse en columnas específicas, no en data
    public $realColumns = [
        'name',
        'email',
        'domain',
        'status',
        'tenancy_db_name',
    ];

    /**
     * Override para manejar el campo data dinámicamente
     * Si existe la columna data, la usa; si no, usa data_placeholder
     */
    public static function getDataColumn(): string
    {
        // Verificar si la columna data existe en la tabla
        try {
            $table = (new static)->getTable();
            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'data')) {
                return 'data';
            }
        } catch (\Exception $e) {
            // Si hay algún error (como que la tabla no existe), usar campo seguro
        }

        // Si no existe la columna data, usar data_placeholder
        return 'data_placeholder';
    }

    /**
     * Override encodeAttributes para evitar problemas cuando no existe el campo data
     */
    protected function encodeAttributes(): void
    {
        $dataColumn = static::getDataColumn();

        // Si estamos usando data_placeholder, no hacer encoding (es solo un placeholder)
        if ($dataColumn === 'data_placeholder') {
            return;
        }

        // Si existe el campo data real, usar el comportamiento normal
        parent::encodeAttributes();
    }

    /**
     * Override getCasts para evitar casts en columnas que no existen
     */
    public function getCasts()
    {
        $casts = parent::getCasts();
        $dataColumn = static::getDataColumn();

        // Si estamos usando data_placeholder, no agregar cast (es nullable json)
        if ($dataColumn === 'data_placeholder') {
            return array_merge($casts, [
                $dataColumn => 'array',
            ]);
        }

        // Si existe el campo data real, agregar el cast normalmente
        return array_merge($casts, [
            $dataColumn => 'array',
        ]);
    }

    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    public function database(): DatabaseConfig
    {
        return new DatabaseConfig($this);
    }

    public function plan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Subscription::class)->where('status', 'active');
    }

    public function hasFeature(string $feature): bool
    {
        return $this->plan?->hasFeature($feature) ?? false;
    }

    public function getLimit(string $limit): int
    {
        return $this->plan?->getLimit($limit) ?? 0;
    }

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    public function trialHasExpired(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isPast();
    }

    /**
     * Get an attribute from the model.
     * Override para leer campos reales desde columnas, no desde data
     */
    public function getAttribute($key)
    {
        // Si es un campo real y existe la columna, leer desde la columna
        if (in_array($key, $this->realColumns) && $this->hasColumn($key)) {
            $value = $this->getAttributes()[$key] ?? null;
            if ($value !== null) {
                return $value;
            }
        }

        // Para otros campos, usar el comportamiento normal
        return parent::getAttribute($key);
    }

    /**
     * Override refresh para asegurar que campos reales se lean desde columnas
     */
    public function refresh()
    {
        parent::refresh();

        // Después del refresh, asegurar que campos reales se lean desde columnas
        foreach ($this->realColumns as $column) {
            if ($this->hasColumn($column)) {
                $value = $this->getOriginal($column);
                if ($value !== null) {
                    $this->attributes[$column] = $value;
                }
            }
        }

        return $this;
    }

    /**
     * Verificar si una columna existe en la tabla
     */
    public function hasColumn($column)
    {
        static $columns = null;
        if ($columns === null) {
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing($this->getTable());
        }
        return in_array($column, $columns);
    }
}