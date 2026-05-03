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
        if (!Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->boolean('is_active')->default(true);
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            // Si la tabla ya existe, agregar columnas faltantes
            if (!Schema::hasColumn('admin_users', 'is_active')) {
                Schema::table('admin_users', function (Blueprint $table) {
                    $table->boolean('is_active')->default(true)->after('password');
                });
            }
            if (!Schema::hasColumn('admin_users', 'deleted_at')) {
                Schema::table('admin_users', function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};
