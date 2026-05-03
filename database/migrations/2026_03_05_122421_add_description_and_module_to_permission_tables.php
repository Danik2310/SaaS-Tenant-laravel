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
        // Agregar columnas a la tabla permissions
        Schema::table('permissions', function (Blueprint $table) {
            $table->text('description')->nullable()->after('guard_name');
            $table->string('module')->nullable()->after('description');
            $table->boolean('is_active')->default(true)->after('module');
        });

        // Agregar columnas a la tabla roles
        Schema::table('roles', function (Blueprint $table) {
            $table->text('description')->nullable()->after('guard_name');
            $table->boolean('is_active')->default(true)->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover columnas de la tabla permissions
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['description', 'module', 'is_active']);
        });

        // Remover columnas de la tabla roles
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['description', 'is_active']);
        });
    }
};
