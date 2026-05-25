<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        throw_if(empty($tableNames), Exception::class, 'Error: config/permission.php not loaded.');

        $prefix = config('database.connections.tenant.prefix', '');

        Schema::table($tableNames['permissions'], function (Blueprint $table) use ($prefix, $tableNames) {
            $t = $prefix.$tableNames['permissions'];
            if (! $this->indexExists($t, 'permissions_guard_name_index')) {
                $table->index('guard_name', 'permissions_guard_name_index');
            }
            if (! $this->indexExists($t, 'permissions_module_index')) {
                $table->index('module', 'permissions_module_index');
            }
            if (! $this->indexExists($t, 'permissions_is_active_index')) {
                $table->index('is_active', 'permissions_is_active_index');
            }
            if (! $this->indexExists($t, 'permissions_guard_module_name_index')) {
                $table->index(['guard_name', 'module', 'name'], 'permissions_guard_module_name_index');
            }
        });

        Schema::table($tableNames['roles'], function (Blueprint $table) use ($prefix, $tableNames) {
            $t = $prefix.$tableNames['roles'];
            if (! $this->indexExists($t, 'roles_guard_name_index')) {
                $table->index('guard_name', 'roles_guard_name_index');
            }
            if (! $this->indexExists($t, 'roles_is_active_index')) {
                $table->index('is_active', 'roles_is_active_index');
            }
            if (! $this->indexExists($t, 'roles_guard_name_name_index')) {
                $table->index(['guard_name', 'name'], 'roles_guard_name_name_index');
            }
        });

        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($prefix, $tableNames) {
            $t = $prefix.$tableNames['model_has_permissions'];
            if (! $this->indexExists($t, 'model_has_permissions_type_model_id_index')) {
                $table->index(['model_type', 'model_id'], 'model_has_permissions_type_model_id_index');
            }
        });

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($prefix, $tableNames) {
            $t = $prefix.$tableNames['model_has_roles'];
            if (! $this->indexExists($t, 'model_has_roles_type_model_id_index')) {
                $table->index(['model_type', 'model_id'], 'model_has_roles_type_model_id_index');
            }
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $prefix = config('database.connections.tenant.prefix', '');

        Schema::table($tableNames['permissions'], function (Blueprint $table) use ($prefix, $tableNames) {
            $t = $prefix.$tableNames['permissions'];
            if ($this->indexExists($t, 'permissions_guard_name_index')) {
                $table->dropIndex('permissions_guard_name_index');
            }
            if ($this->indexExists($t, 'permissions_module_index')) {
                $table->dropIndex('permissions_module_index');
            }
            if ($this->indexExists($t, 'permissions_is_active_index')) {
                $table->dropIndex('permissions_is_active_index');
            }
            if ($this->indexExists($t, 'permissions_guard_module_name_index')) {
                $table->dropIndex('permissions_guard_module_name_index');
            }
        });

        Schema::table($tableNames['roles'], function (Blueprint $table) use ($prefix, $tableNames) {
            $t = $prefix.$tableNames['roles'];
            if ($this->indexExists($t, 'roles_guard_name_index')) {
                $table->dropIndex('roles_guard_name_index');
            }
            if ($this->indexExists($t, 'roles_is_active_index')) {
                $table->dropIndex('roles_is_active_index');
            }
            if ($this->indexExists($t, 'roles_guard_name_name_index')) {
                $table->dropIndex('roles_guard_name_name_index');
            }
        });

        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($prefix, $tableNames) {
            $t = $prefix.$tableNames['model_has_permissions'];
            if ($this->indexExists($t, 'model_has_permissions_type_model_id_index')) {
                $table->dropIndex('model_has_permissions_type_model_id_index');
            }
        });

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($prefix, $tableNames) {
            $t = $prefix.$tableNames['model_has_roles'];
            if ($this->indexExists($t, 'model_has_roles_type_model_id_index')) {
                $table->dropIndex('model_has_roles_type_model_id_index');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return ! empty(Schema::getConnection()->select(
            "SHOW INDEXES FROM `{$table}` WHERE `Key_name` = ?",
            [$index]
        ));
    }
};
