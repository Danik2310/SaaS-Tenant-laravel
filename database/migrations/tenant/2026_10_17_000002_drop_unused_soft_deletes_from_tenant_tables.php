<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the `deleted_at` columns added to tenant tables by
 * 2026_05_28_070420_add_soft_deletes_to_tenant_tables.
 *
 * None of the corresponding models (Category, Warehouse, InventoryMovement,
 * Payment) use the SoftDeletes trait, and the columns are never queried, so
 * they only add write overhead on every INSERT/UPDATE. The remaining tenant
 * models that do soft-delete (Product, Customer) keep their own columns.
 */
return new class extends Migration
{
    private const TABLES = [
        'categories',
        'warehouses',
        'inventory_movements',
        'payments',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropSoftDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->softDeletes();
                });
            }
        }
    }
};
