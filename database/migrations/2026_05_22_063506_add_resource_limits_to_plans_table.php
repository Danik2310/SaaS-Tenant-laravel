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
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('max_warehouses')->nullable()->after('max_storage');
            $table->unsignedInteger('max_categories')->nullable()->after('max_warehouses');
            $table->unsignedInteger('max_products')->nullable()->after('max_categories');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['max_products', 'max_categories', 'max_warehouses']);
        });
    }
};
