<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Leftmost-prefix duplicates replaced by wider compound indexes:
        // - products.active               ⊆ products[active, created_at]
        // - permissions.guard_name        ⊆ permissions[guard_name, module, name]
        // - roles.guard_name              ⊆ roles[guard_name, name]
        $tableNames = config('permission.table_names', []);
        $permissionsTable = $tableNames['permissions'] ?? 'permissions';
        $rolesTable = $tableNames['roles'] ?? 'roles';
        $prefix = config('database.connections.tenant.prefix', '');

        if (Schema::hasTable('products') && $this->indexExists($prefix.'products', 'products_active_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_active_index');
            });
        }

        if (Schema::hasTable($permissionsTable) && $this->indexExists($prefix.$permissionsTable, 'permissions_guard_name_index')) {
            Schema::table($permissionsTable, function (Blueprint $table) {
                $table->dropIndex('permissions_guard_name_index');
            });
        }

        if (Schema::hasTable($rolesTable) && $this->indexExists($prefix.$rolesTable, 'roles_guard_name_index')) {
            Schema::table($rolesTable, function (Blueprint $table) {
                $table->dropIndex('roles_guard_name_index');
            });
        }
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names', []);
        $permissionsTable = $tableNames['permissions'] ?? 'permissions';
        $rolesTable = $tableNames['roles'] ?? 'roles';
        $prefix = config('database.connections.tenant.prefix', '');

        if (Schema::hasTable('products') && ! $this->indexExists($prefix.'products', 'products_active_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index('active', 'products_active_index');
            });
        }

        if (Schema::hasTable($permissionsTable) && ! $this->indexExists($prefix.$permissionsTable, 'permissions_guard_name_index')) {
            Schema::table($permissionsTable, function (Blueprint $table) {
                $table->index('guard_name', 'permissions_guard_name_index');
            });
        }

        if (Schema::hasTable($rolesTable) && ! $this->indexExists($prefix.$rolesTable, 'roles_guard_name_index')) {
            Schema::table($rolesTable, function (Blueprint $table) {
                $table->index('guard_name', 'roles_guard_name_index');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return ! empty(Schema::getConnection()->select(
            "SHOW INDEXES FROM `{$table}` WHERE `Key_name` = ?",
            [$index]
        ));
    }
};
