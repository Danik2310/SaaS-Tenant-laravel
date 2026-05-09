<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the same columns to tenant permission/role tables that exist
     * in the central permission/role tables, fixing schema drift.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        throw_if(empty($tableNames), Exception::class, 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        // Add columns to permissions table
        Schema::table($tableNames['permissions'], function (Blueprint $table) {
            $table->text('description')->nullable()->after('guard_name');
            $table->string('module')->nullable()->after('description');
            $table->boolean('is_active')->default(true)->after('module');
        });

        // Add columns to roles table
        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->text('description')->nullable()->after('guard_name');
            $table->boolean('is_active')->default(true)->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['permissions'] ?? 'permissions', function (Blueprint $table) {
            $columns = ['description', 'module', 'is_active'];
            foreach ($columns as $column) {
                if (Schema::hasColumn($tableNames['permissions'] ?? 'permissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table($tableNames['roles'] ?? 'roles', function (Blueprint $table) {
            if (Schema::hasColumn($tableNames['roles'] ?? 'roles', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn($tableNames['roles'] ?? 'roles', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
