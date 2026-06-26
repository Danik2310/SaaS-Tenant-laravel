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
            if (! Schema::hasColumn('plans', 'max_warehouses')) {
                $table->unsignedInteger('max_warehouses')->nullable()->after('max_storage');
            }
            if (! Schema::hasColumn('plans', 'max_categories')) {
                $table->unsignedInteger('max_categories')->nullable()->after('max_warehouses');
            }
            if (! Schema::hasColumn('plans', 'max_products')) {
                $table->unsignedInteger('max_products')->nullable()->after('max_categories');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('plans', 'max_warehouses')) {
                $columns[] = 'max_warehouses';
            }
            if (Schema::hasColumn('plans', 'max_categories')) {
                $columns[] = 'max_categories';
            }
            if (Schema::hasColumn('plans', 'max_products')) {
                $columns[] = 'max_products';
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
