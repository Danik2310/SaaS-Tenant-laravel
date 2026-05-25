<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add columns to permissions table — skip if already present
        Schema::table('permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('permissions', 'description')) {
                $table->text('description')->nullable()->after('guard_name');
            }
            if (! Schema::hasColumn('permissions', 'module')) {
                $table->string('module')->nullable()->after('description');
            }
            if (! Schema::hasColumn('permissions', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('module');
            }
        });

        // Add columns to roles table — skip if already present
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'description')) {
                $table->text('description')->nullable()->after('guard_name');
            }
            if (! Schema::hasColumn('roles', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop columns from permissions table — only if they exist
        Schema::table('permissions', function (Blueprint $table) {
            $columns = array_intersect(
                ['description', 'module', 'is_active'],
                Schema::getColumnListing('permissions')
            );
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });

        // Drop columns from roles table — only if they exist
        Schema::table('roles', function (Blueprint $table) {
            $columns = array_intersect(
                ['description', 'is_active'],
                Schema::getColumnListing('roles')
            );
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
